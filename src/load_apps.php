<?php

use \CFPropertyList\CFPropertyList;
use \CFPropertyList\CFDictionary;
use \CFPropertyList\CFArray;
use \CFPropertyList\CFString;
use \CFPropertyList\CFBoolean;

function load_apps($path = 'apps')
{
    $url = rtrim("https://$_SERVER[HTTP_HOST]" . dirname($_SERVER['SCRIPT_NAME']), '/');
    $apps = array();

    foreach (glob("$path/*.ipa") as $ipa) {
        $app   = substr($ipa, 0, -4);
        $icon  = "$app.png";
        $plist = "$app.plist";

        if (!file_exists($plist)) {
            // Extract plist from ipa
            $zip = zip_open($ipa);
            if (!is_resource($zip)) {
                // echo "ERROR: Could not open $ipa ($zip)";
                continue;
            }

            $info_plist = false;

            while ($entry = zip_read($zip)) {
                $entry_name = zip_entry_name($entry);

                // Extract Plist
                if (preg_match('/Payload\/([ a-zA-Z0-9.-_]+)\.app\/Info\.plist/', $entry_name)
                    && zip_entry_filesize($entry)) {
                    // found non-empty Info.plist
                    if (zip_entry_open($zip, $entry)) {
                        $contents = zip_entry_read($entry, zip_entry_filesize($entry));
                        $info_plist = new CFPropertyList();
                        $info_plist->parse($contents);
                    }
                    zip_entry_close($entry);
                }

                // Extract Icon File
                if (preg_match('/Payload\/([ a-zA-Z0-9.-_]+).app\/[Ii]con\.png/', $entry_name)
                    || preg_match('/Payload\/([ a-zA-Z0-9.-_]+).app\/[Ii]con@2x\.png/', $entry_name)
                    && zip_entry_filesize($entry)) {
                    // found icon file
                    if (zip_entry_open($zip, $entry)) {
                        $iconContents = zip_entry_read($entry, zip_entry_filesize($entry));
                        file_put_contents($icon, $iconContents);
                    }
                    zip_entry_close($entry);
                }
            }

            zip_close($zip);

            if (!$info_plist) {
                // Could not find Info.plist in IPA - skipping app...
                continue;
            }

            // Create Manifest Plist
            $manifest = new CFPropertyList();
            $manifest->add($dict = new CFDictionary());
            $dict->add('items', $array = new CFArray());
            $array->add($dictItem1 = new CFDictionary());

            // Assets
            $dictItem1->add('assets', $arrayAssets = new CFArray());

            $arrayAssets->add($dictAsset1 = new CFDictionary());
            $dictAsset1->add('kind', new CFString('software-package'));
            $dictAsset1->add('url', new CFString("$url/apps/$ipa"));

            $arrayAssets->add($dictAsset2 = new CFDictionary());
            $dictAsset2->add('kind', new CFString('display-image'));
            $dictAsset2->add('needs-shine', new CFBoolean(false));

            if (file_exists($icon)) {
                $dictAsset2->add('url', new CFString("$url/$icon"));
            } else {
                $dictAsset2->add('url', new CFString("$url/img/default-display-image.png"));
            }

            $arrayAssets->add($dictAsset3 = new CFDictionary());
            $dictAsset3->add('kind', new CFString('full-sized-image'));
            $dictAsset3->add('needs-shine', new CFBoolean(false));
            $dictAsset3->add('url', new CFString("$url/img/default-full-size-image.png"));

            // Metadata
            $dictItem1->add('metadata', $dictMeta = new CFDictionary());

            $dictMeta->add('bundle-identifier', $info_plist->getValue()->get('CFBundleIdentifier'));
            $dictMeta->add('bundle-version', $info_plist->getValue()->get('CFBundleVersion'));
            $dictMeta->add('kind', new CFString('software'));
            $dictMeta->add('title', $info_plist->getValue()->get('CFBundleName'));

            // Save Manifest Plist
            $manifest->saveXML($plist);
        } // end app resource extraction

        // Load App Information
        $ipa_plist = new CFPropertyList($plist);
        $ipa_plist = $ipa_plist->toArray();

        $data = [
            'metadata' => $ipa_plist['items'][0]['metadata'],
            'assets' => ['manifest' => "$url/$plist"],
        ];

        foreach ($ipa_plist['items'][0]['assets'] as $asset) {
            $data['assets'][$asset['kind']] = $asset['url'];
        }

        $data['metadata']['size'] = human_filesize(filesize($ipa));
        $data['metadata']['updated'] = date('M j, Y', filemtime($ipa));

        $apps[$app] = $data;
    }

    return $apps;
}

function human_filesize($bytes, $decimals = 2)
{
    $sz = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor];
}
