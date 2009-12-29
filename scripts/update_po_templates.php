#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

function update_core($dir, $domain)
{
    $old = getcwd();
    chdir($dir);
    passthru(<<<END
xgettext \
    --from-code=UTF-8 \
    --default-domain=$domain \
    --output=locale/$domain.po \
    --language=PHP \
    --keyword="_m:1" \
    --keyword="pgettext:1c,2" \
    --keyword="npgettext:1c,2,3" \
    actions/*.php \
    classes/*.php \
    lib/*.php \
    scripts/*.php
END
);
    chdir($old);
}

function do_update_plugin($dir, $domain)
{
    $old = getcwd();
    chdir($dir);
    if (!file_exists('locale')) {
        mkdir('locale');
    }
    $files = get_plugin_sources(".");
    $cmd = <<<END
xgettext \
    --from-code=UTF-8 \
    --default-domain=$domain \
    --output=locale/$domain.po \
    --language=PHP \
    --keyword='' \
    --keyword="_m:1" \

END;
    foreach ($files as $file) {
      $cmd .= ' ' . escapeshellarg($file);
    }
    passthru($cmd);
    chdir($old);
}

function do_translatewiki_plugin($basedir, $plugin)
{
    $yamldir = "$basedir/locale/TranslateWiki";
    if (!file_exists($yamldir)) {
        mkdir($yamldir);
    }
    $outfile = "$yamldir/StatusNet-{$plugin}.yml";
    $data = <<<END
---
BASIC:
  id: out-statusnet-{$plugin}
  label: StatusNet - {$plugin}
  description: "{{int:bw-desc-statusnet-plugin-{$plugin}}}"
  namespace: NS_STATUSNET
  display: out/statusnet/{$plugin}
  class: GettextMessageGroup

FILES:
  class: GettextFFS
  sourcePattern: %GROUPROOT%/plugins/{$plugin}/locale/%CODE%/LC_MESSAGES/{$plugin}.po
  targetPattern: {$plugin}.po
  codeMap:
    en-gb: en_GB
    no: nb
    pt-br: pt_BR
    zh-hans: zh_CN
    zh-hant: zh_TW

MANGLER
  class: StringMatcher
  prefix: {$plugin}-
  patterns:
    - "*"

END;
    file_put_contents($outfile, $data);
}

function get_plugins($dir)
{
    $plugins = array();
    $dirs = new DirectoryIterator("$dir/plugins");
    foreach ($dirs as $item) {
        if ($item->isDir() && !$item->isDot()) {
            $name = $item->getBasename();
            if (file_exists("$dir/plugins/$name/{$name}Plugin.php")) {
                $plugins[] = $name;
            }
        }
    }
    return $plugins;
}

function get_plugin_sources($dir)
{
    $files = array();

    $dirs = new RecursiveDirectoryIterator($dir);
    $iter = new RecursiveIteratorIterator($dirs);
    foreach ($iter as $pathname => $item) {
        if ($item->isFile() && preg_match('/\.php$/', $item->getBaseName())) {
            $files[] = $pathname;
        }
    }
    return $files;
}

function plugin_using_gettext($dir)
{
    $files = get_plugin_sources($dir);
    foreach ($files as $pathname) {
        // Check if the file is using our _m gettext wrapper
        $code = file_get_contents($pathname);
        if (preg_match('/\b_m\(/', $code)) {
            return true;
        }
    }

    return false;
}

function update_plugin($basedir, $name)
{
    $dir = "$basedir/plugins/$name";
    if (plugin_using_gettext($dir)) {
        do_update_plugin($dir, $name);
        do_translatewiki_plugin($basedir, $name);
        return true;
    } else {
        return false;
    }
}

$args = $_SERVER['argv'];
array_shift($args);

$all = false;
$core = false;
$allplugins = false;
$plugins = array();
if (count($args) == 0) {
    $all = true;
}
foreach ($args as $arg) {
    if ($arg == '--all') {
        $all = true;
    } elseif ($arg == "--core") {
        $core = true;
    } elseif ($arg == "--plugins") {
        $allplugins = true;
    } elseif (substr($arg, 0, 9) == "--plugin=") {
        $plugins[] = substr($arg, 9);
    }
}



if ($all || $core) {
    echo "core...";
    update_core(INSTALLDIR, 'statusnet');
    echo " ok\n";
}
if ($all || $allplugins) {
    $plugins = get_plugins(INSTALLDIR);
}
if ($plugins) {
    foreach ($plugins as $plugin) {
        echo "$plugin...";
        if (update_plugin(INSTALLDIR, $plugin)) {
            echo " ok\n";
        } else {
            echo " not localized\n";
        }
    }
}
