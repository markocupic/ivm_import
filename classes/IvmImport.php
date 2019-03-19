<?php

/*
 * This file is part of Marko Cupic IVM Package.
 *
 * (c) Marko Cupic, 19.03.2019
 * @author Marko Cupic <https://github.com/markocupic/ivm_import>
 * @contact m.cupic@gmx.ch
 * @license Commercial
 */

namespace Markocupic\Ivm;

use Contao\Database;
use Contao\Date;
use Contao\Folder;
use Contao\StringUtil;
use Contao\Input;
use Contao\System;

/**
 * Class IvmImport
 * @package Markocupic\Ivm
 */
class IvmImport
{
    /**
     * Force image download default to false
     * @var bool
     */
    protected $force = false;

    /**
     * @var string
     */
    protected $jsonIvmUrl = 'https://wg-dessau.ivm-professional.de';

    /**
     * @var string
     */
    protected $downloadFolder = 'files/Wohnungsangebote';

    /**
     * @var string
     */
    protected $imagePath;

    /**
     * Import database from https://wg-dessau.ivm-professional.de
     * Contao initializeSystem Hook
     * Normally the script will be launched by a cronjob
     * https://yourhost.de?ivmImport=true or https://yourhost.de?ivmImport=true&force=true
     * If you run the cronjob with the force=true parameter, the download folder will be purged first
     */
    public function importDatabase()
    {
        if (strlen(Input::get('ivmImport')))
        {
            $startTime = time();

            // Folder settings
            $this->imagePath = TL_ROOT . '/' . $this->downloadFolder . '/';

            echo '<pre>';

            if (strlen(Input::get('force')))
            {
                echo "Import Skript mit dem force=true PArameter aufgerufen...\n\n";
                $this->force = true;
            }

            // Create download folder and unprotect it
            $objDownloadFolder = new Folder($this->downloadFolder);
            if (version_compare(VERSION, '3.5', '>'))
            {
                if (!file_exists(TL_ROOT . '/' . $this->downloadFolder . '/.public'))
                {
                    echo "Mache Download-Ordner " . $this->downloadFolder . " öffentlich...\n\n";
                    $fp = fopen(TL_ROOT . '/' . $this->downloadFolder . '/.public', 'w');
                    fwrite($fp, sprintf('Erstellt am %s durch %s Linie %s', Date::parse('Y.m.d'), __METHOD__, __LINE__));
                    fclose($fp);
                }
            }

            // Purge download folder
            if ($this->force)
            {
                echo "Download Verzeichnis leeren " . $this->downloadFolder . "...\n\n";
                $objDownloadFolder->purge();
            }

            // Truncate tables
            echo "Tabellen is_details, is_wohnungen, is_wohngebiete werden geleert...\n\n";
            Database::getInstance()->query('TRUNCATE TABLE is_details');
            Database::getInstance()->query('TRUNCATE TABLE is_wohnungen');
            Database::getInstance()->query('TRUNCATE TABLE is_wohngebiete');
            // Database::getInstance()->query('TRUNCATE TABLE is_ansprechpartner');

            // Add columns is_wohnungen.flat_id & is_wohnungen.gallery_img
            if (!Database::getInstance()->fieldExists('flat_id', 'is_wohnungen'))
            {
                Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `flat_id` INT NOT NULL');
            }

            if (!Database::getInstance()->fieldExists('gallery_img', 'is_wohnungen'))
            {
                Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `gallery_img` BLOB NOT NULL');
            }

            // Let's start the import process...
            echo "Starte den Importvorgang...\n\n";

            // Get Ausstattungen
            $data_raw = file_get_contents($this->jsonIvmUrl . "/modules/json/json_environments.php");
            $ausstattungen = StringUtil::deserialize(json_decode($data_raw), true);
            echo count($ausstattungen) . " Ausstattungen geladen\n\n";

            // Import Wohngebiete
            $data_raw = file_get_contents($this->jsonIvmUrl . "/modules/json/json_districts.php");
            $data = StringUtil::deserialize(json_decode($data_raw), true);

            $arr_wohngebiete = array();
            echo "Importiere Wohngebiete...\n";
            foreach ($data as $key => $value)
            {
                $set = array(
                    "id"         => $key,
                    "wohngebiet" => $value['name']
                );
                $stm = Database::getInstance()->prepare("INSERT INTO is_wohngebiete %s")->set($set)->execute();
                if ($stm->affectedRows)
                {
                    $arr_wohngebiete[$value['name']] = $stm->insertId;
                }
            }
            echo count($arr_wohngebiete) . " Wohngebiete geladen.\n\n";

            // Get Top-Wohnungen
            echo "Importiere Top-Wohnungen...\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->jsonIvmUrl . "/modules/json/json_search.php");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                'search_page' => 1,
                'tafel'       => 1
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $top_wohnungen = array();
            $data = json_decode($response, true);
            echo count($data) . " Top-Wohnungen importiert.\n\n";
            foreach ($data['flats'] as $key => $value)
            {
                $top_wohnungen[$value['flat_id']] = true;
            }

            // Import Wohnungen
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->jsonIvmUrl . "/modules/json/json_search.php");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                'search_page' => $_GET['page'] ? $_GET['page'] : 1,
                'limit'       => 'all', //Das ist sehr wichtig!!!!!!!!!!!!!!!!!
                'tafel'       => 0
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);

            if (is_array($data['flats']))
            {
                echo count($data['flats']) . " Wohnungsangebote werden importiert\n";

                foreach ($data['flats'] as $key => $value)
                {
                    echo "\nImportiere Angebot {$value['flat_id']}\n";
                    $pics = array();

                    // Get Details
                    $ch = curl_init();
                    curl_setopt_array($ch, array(
                        CURLOPT_HEADER         => 0,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POSTFIELDS     => array('flat_id' => $value['flat_id']),
                        CURLOPT_TIMEOUT        => 60 * 5,
                        CURLOPT_URL            => $this->jsonIvmUrl . "/modules/json/json_details.php",
                    ));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    // Get gallery images
                    $objDetails = json_decode($response);
                    $gallery_img = urldecode($objDetails->gallery_img);
                    echo "Importiere Galerie: " . $gallery_img . "...\n";

                    //$value['environmet'] thats a typo, but it is made by IVM-Professional ;-);-)
                    $environment = StringUtil::deserialize(urldecode($value['environmet']), true);

                    if ($this->force || !file_exists($this->imagePath . $value['image']))
                    {
                        echo "Lade Bild " . $value['image'] . "\n";
                        $ch = curl_init();
                        curl_setopt_array($ch, array(
                            CURLOPT_FILE    => fopen($this->imagePath . $value['image'], 'w'),
                            CURLOPT_TIMEOUT => 60 * 10,
                            CURLOPT_URL     => $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/flats/' . urlencode($value['image']) . '&w=1024&h=1024',
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                    }
                    $pics[] = $value['image'];

                    if ($value['flat_plot'])
                    {
                        if ($this->force || !file_exists($this->imagePath . $value['flat_plot']))
                        {
                            echo "Lade Grundriss " . $value['flat_plot'] . "\n";
                            $ch = curl_init();
                            curl_setopt_array($ch, array(
                                CURLOPT_FILE    => fopen($this->imagePath . $value['flat_plot'], 'w'),
                                CURLOPT_TIMEOUT => 60 * 5,
                                CURLOPT_URL     => $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot']) . '&w=1024',
                            ));
                            curl_exec($ch);
                            curl_close($ch);
                        }
                        $pics[] = $value['flat_plot'];
                    }

                    if ($value['flat_plot2'])
                    {
                        if ($this->force || !file_exists($this->imagePath . $value['flat_plot']))
                        {
                            echo "Lade Grundriss 2 " . $value['flat_plot2'] . "\n";
                            $ch = curl_init();
                            curl_setopt_array($ch, array(
                                CURLOPT_FILE    => fopen($this->imagePath . $value['flat_plot2'], 'w'),
                                CURLOPT_TIMEOUT => 60 * 5,
                                CURLOPT_URL     => $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot2']) . '&w=1024',
                            ));
                            curl_exec($ch);
                            curl_close($ch);
                        }
                        $pics[] = $value['flat_plot2'];
                    }

                    if ($this->force || !file_exists($this->imagePath . 'expose_' . $value['flat_id'] . '.pdf'))
                    {
                        echo "Lade Expose " . $value['flat_pdf'] . "\n";
                        $ch = curl_init();
                        curl_setopt_array($ch, array(
                            CURLOPT_FILE    => fopen($this->imagePath . 'expose_' . $value['flat_id'] . '.pdf', 'w'),
                            CURLOPT_TIMEOUT => 60 * 5,
                            CURLOPT_URL     => $this->jsonIvmUrl . '/make_pdf/make_pdf.php?flat_id=' . $value['flat_id'],
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                    }

                    $ansprechpartner = null;
                    if ($value['arranger'] && $value['arranger_email'])
                    {
                        $stm = Database::getInstance()->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%" . $value['arranger'] . "%' OR email LIKE '%" . $value['arranger_email'] . "%'")->limit(1)->execute();
                        if ($stm->numRows)
                        {
                            $ansprechpartner = $stm->row();
                        }
                    }
                    else
                    {
                        $stm = Database::getInstance()->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%Wohnungsgenossenschaft%'")->limit(1)->execute();
                        if ($stm->numRows)
                        {
                            $ansprechpartner = $stm->row();
                        }
                    }

                    if (!isset($ansprechpartner['id']))
                    {
                        echo "Kein Ansprechpartner für " . $value['arranger'] . ' ' . $value['arranger_email'] . "<br>";
                    }

                    // Prepare some fields
                    $value['objectdescription'] = join("\n", $environment) . "\n" . $value['objectdescription'];
                    $value['objectdescription'] .= "\n" . $value['note'];

                    $set = array(
                        "title"           => $value['flat_exposetitle'],
                        "strasse"         => $value['street'],
                        "hnr"             => $value['streetnumber'] ? $value['streetnumber'] : '',
                        "plz"             => $value['zip'],
                        "ort"             => $value['city'],
                        "nk"              => $this->formatNumber($value['charges']),
                        "hk"              => $this->formatNumber($value['heating']),
                        //"hk_in" => $value['heating']==0 ? 'Ja' : 'Nein',
                        "hk_in"           => 'Ja',
                        "beschr"          => $value['objectdescription'],
                        "beschr_lage"     => $value['district_description'],
                        "sonstige"        => ($value['flat_note'] || $value['flat_special_text']) ? nl2br($value['flat_note'] . '<br>' . $value['flat_special_text']) : '',
                        "typ"             => $value['portal_wohnungstyp'] && $value['portal_wohnungstyp'] != 'NO_INFORMATION' ? $value['portal_wohnungstyp'] : '',
                        "objektnr"        => $value['flat_keynumber'],
                        "baujahr"         => $value['flat_year'] ? $value['flat_year'] : '',
                        "pics"            => join(';', $pics),
                        "fern"            => preg_match('/fern/i', $value['flat_lights']) ? 'true' : '',
                        "gas"             => preg_match('/gas/i', $value['flat_lights']) ? 'true' : '',
                        "fenster"         => $environment[9] ? "true" : "",
                        "offen"           => $environment[1] || $environment[43] ? "true" : "",
                        "fliesen"         => '',
                        "kunststoff"      => '',
                        "parkett"         => '',
                        "teppich"         => '',
                        "laminat"         => '',
                        "dielen"          => '',
                        "etage_heizung"   => '',
                        "zentral"         => '',
                        "keller"          => '',
                        "verfuegbar"      => '',
                        "barrierefrei"    => $environment[17] ? "true" : "",
                        "wg"              => '',
                        "expose"          => 'expose_' . $value['flat_id'] . '.pdf',
                        "eausweis"        => $value['flat_enev_ausweisart'] ? $value['flat_enev_ausweisart'] : '',
                        "everbrauchswert" => $this->formatNumber($value['flat_enev_verbrauchswert']),
                        "ebedarfswert"    => $this->formatNumber($value['flat_enev_ebedarfswert']),
                        "eheizung"        => $value['flat_lights'] ? $value['flat_lights'] : '',
                        "ausstattung"     => join(', ', $environment)
                    );
                    $stm = Database::getInstance()->prepare("INSERT INTO is_details %s")->set($set)->execute();
                    if ($stm->affectedRows)
                    {
                        $wid = $stm->insertId;
                        $set = array(
                            "wid"         => $wid,
                            "gid"         => $arr_wohngebiete[$value['district_name']],
                            "aid"         => $ansprechpartner['id'] ? $ansprechpartner['id'] : 1,
                            "zimmer"      => $value['rooms'],
                            "flaeche"     => $this->formatNumber($value['space']),
                            "warm"        => $this->formatNumber($value['rent_all']),
                            "kalt"        => $this->formatNumber($value['rent']),
                            "etage"       => preg_replace("/\.Etage/", "", $value['floor']),
                            "kaution"     => $this->formatNumber($value['flat_deposit']),
                            "dusche"      => $environment[7] ? "true" : "",
                            "wanne"       => $environment[8] ? "true" : "",
                            "balkon"      => $environment[14] ? "Balkon" : ($environment[16] ? "Terrasse" : ""),
                            "lift"        => $environment[18] ? "true" : "",
                            "garten"      => $environment[23] ? "true" : "",
                            "ebk"         => $environment[3] ? "true" : "",
                            "top"         => $top_wohnungen[$value['flat_id']] ? 1 : 0,
                            "flat_id"     => $value['flat_id'],
                            "gallery_img" => $gallery_img,
                        );
                        Database::getInstance()->prepare("INSERT INTO is_wohnungen %s")->set($set)->execute();
                    }
                }

                echo "\n" . count($data['flats']) . " Wohnungen importiert\n";
                System::log(count($data['flats']) . " Wohnungen importiert", __METHOD__, TL_GENERAL);
            }
            else
            {
                echo "\nKeine Wohnungen importiert.\n";
            }

            echo "\n" . sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime) . "\n";
            System::log(sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime), __METHOD__, TL_GENERAL);
            echo '</pre>';
            exit();
        }
    }

    /**
     * @param $number
     * @return float
     */
    private function formatNumber($number)
    {
        return doubleval(preg_replace("/,/", ".", $number));
    }
}

