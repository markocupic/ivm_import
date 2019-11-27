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
    protected $blnForce = false;

    /**
     * @var bool
     */
    protected $blnPurgeDownloadFolder = false;

    /**
     * @var string
     */
    protected $page;

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
     *
     * @param string $page
     * @param bool $blnForce
     * @param bool $blnPurgeDownloadFolder
     * @throws \Exception
     */
    public function importIvmDatabase($page = '', $blnForce = false, $blnPurgeDownloadFolder = false)
    {
        $startTime = time();

        echo '<pre>';

        $this->page = $page >= 1 ? $page : '';
        if ($this->page)
        {
            echo sprintf("Import Skript mit dem page=%s Parameter aufgerufen...", $this->page) . "\n\n";
        }

        $this->blnForce = $blnForce === true ? true : false;
        if ($this->blnForce)
        {
            echo "Import Skript mit dem force=true Parameter aufgerufen...\n\n";
        }

        $this->blnPurgeDownloadFolder = $blnPurgeDownloadFolder === true ? true : false;
        if ($this->blnPurgeDownloadFolder)
        {
            echo "Import Skript mit dem blnPurgeDownloadFolder=true Parameter aufgerufen...\n\n";
        }

        // Folder settings
        $this->imagePath = TL_ROOT . '/' . $this->downloadFolder;

        // Create download folder and unprotect it
        $objDownloadFolder = new \Folder($this->downloadFolder);
        if (version_compare(VERSION, '3.5', '>'))
        {
            if (!file_exists(TL_ROOT . '/' . $this->downloadFolder . '/.public'))
            {
                echo "Mache Download-Ordner " . $this->downloadFolder . " öffentlich...\n\n";
                $fp = fopen(TL_ROOT . '/' . $this->downloadFolder . '/.public', 'w');
                fwrite($fp, sprintf('Erstellt am %s durch %s Linie %s', \Date::parse('Y.m.d'), __METHOD__, __LINE__));
                fclose($fp);
            }
        }

        // Purge download folder
        if ($this->blnPurgeDownloadFolder && ($this->page == '' || $this->page == 1))
        {
            echo "Download Verzeichnis leeren " . $this->downloadFolder . "...\n\n";
            $objDownloadFolder->purge();
        }

        // Truncate tables
        if ($this->page == 1 || $this->page == '')
        {
            echo "Tabelle is_details wird geleert...\n\n";
            \Database::getInstance()->query('TRUNCATE TABLE is_details');

            echo "Tabelle is_wohnungen wird geleert...\n\n";
            \Database::getInstance()->query('TRUNCATE TABLE is_wohnungen');

            echo "Tabelle is_wohngebiete wird geleert...\n\n";
            \Database::getInstance()->query('TRUNCATE TABLE is_wohngebiete');

            echo "Tabelle is_ansprechpartner wird geleert...\n\n";
            \Database::getInstance()->query('TRUNCATE TABLE is_ansprechpartner');
        }

        // Add more fields to is_details, is_wohngebiete, is_ansprechpartner, is_wohnungen, etc.
        $this->extendTables();

        // Let's start the import process...
        echo "Starte den Importvorgang...\n\n";

        // Get Ausstattungen
        $data_raw = file_get_contents($this->jsonIvmUrl . "/modules/json/json_environments.php");
        $ausstattungen = self::deserialize(json_decode($data_raw), true);
        echo count($ausstattungen) . " Ausstattungen geladen\n\n";

        // Import Wohngebiete
        $data_raw = file_get_contents($this->jsonIvmUrl . "/modules/json/json_districts.php");
        $data = self::deserialize(json_decode($data_raw), true);

        $arr_wohngebiete = array();
        echo "Importiere Wohngebiete...\n";
        foreach ($data as $key => $value)
        {
            $set = array(
                "id"         => $key,
                "wohngebiet" => $value['name']
            );
            $stm = \Database::getInstance()->prepare("INSERT INTO is_wohngebiete %s")->set($set)->execute();
            if ($stm->affectedRows)
            {
                $arr_wohngebiete[$value['name']] = $stm->insertId;
            }
        }
        echo count($arr_wohngebiete) . " Wohngebiete geladen.\n\n";

        // Get Top-Wohnungen
        // test  echo "Importiere Top-Wohnungen...\n";
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
        if (!empty($data['flats']) && is_array($data['flats']))
        {
            foreach ($data['flats'] as $key => $value)
            {
                $top_wohnungen[$value['flat_id']] = true;
            }
        }

        // Import flats
        $arrCurlOpt = array('tafel' => 0);
        if ($this->page != '')
        {
            $arrCurlOpt['search_page'] = $this->page;
        }
        if ($this->page == '')
        {
            $arrCurlOpt['limit'] = 'all';
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->jsonIvmUrl . "/modules/json/json_search.php");
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $arrCurlOpt);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (is_array($data['flats']))
        {
            // test      echo count($data['flats']) . " Wohnungsangebote werden importiert\n";

            foreach ($data['flats'] as $key => $value)
            {
                // test        echo "\nImportiere Angebot {$value['flat_id']}\n";
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
                // test        echo "Importiere Galerie: " . $gallery_img . "...\n";

                //$value['environmet'] thats a typo, but it was made by IVM-Professional ;-);-)
                $environment = self::deserialize(urldecode($value['environmet']), true);

                if ($this->blnForce || !file_exists($this->imagePath . '/' . $value['image']))
                {
                    if (strlen($value['image']))
                    {
                        // test             echo "Lade Bild " . $value['image'] . "\n";
                        $curloptUrl = $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/flats/' . urlencode($value['image']) . '&w=1024&h=1024';
                        $curloptFile = $this->imagePath . '/' . $value['image'];
                        $this->curlFileDownload($curloptFile, $curloptUrl, 600);
                    }
                }
                $pics[] = $value['image'];

                // Get flat plot
                if ($value['flat_plot'])
                {
                    if ($this->blnForce || !file_exists($this->imagePath . '/' . $value['flat_plot']))
                    {
                        if (strlen($value['flat_plot']))
                        {
                            // test              echo "Lade Grundriss " . $value['flat_plot'] . "\n";
                            $curloptUrl = $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot']) . '&w=1024';
                            $curloptFile = $this->imagePath . '/' . $value['flat_plot'];
                            $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                        }
                    }
                    $pics[] = $value['flat_plot'];
                }

                // Get flat plot 2
                if ($value['flat_plot2'])
                {
                    if ($this->blnForce || !file_exists($this->imagePath . '/' . $value['flat_plot2']))
                    {
                        if (strlen($value['flat_plot2']))
                        {
                            // test               echo "Lade Grundriss 2 " . $value['flat_plot2'] . "\n";
                            $curloptUrl = $this->jsonIvmUrl . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot2']) . '&w=1024';
                            $curloptFile = $this->imagePath . '/' . $value['flat_plot2'];
                            $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                        }
                    }
                    $pics[] = $value['flat_plot2'];
                }

                if ($this->blnForce || !file_exists($this->imagePath . '/' . 'expose_' . $value['flat_id'] . '.pdf'))
                {
                    // test           echo "Lade Expose expose_" . $value['flat_id'] . ".pdf" . "\n";
                    $curloptUrl = $this->jsonIvmUrl . '/make_pdf/make_pdf.php?flat_id=' . $value['flat_id'];
                    $curloptFile = $this->imagePath . '/' . 'expose_' . $value['flat_id'] . '.pdf';
                    $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                }

                // Update table is_ansprechpartner
                if ($value['arrangernr'])
                {
                    $this->updateTableAnsprechpartner($value['arrangernr'], $value);
                }

                // Ansprechpartner
                $arrAnsprechpartner = null;
                if ($value['arrangernr'])
                {
                    $stm = \Database::getInstance()->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")->limit(1)->execute($value['arrangernr']);
                    if ($stm->numRows)
                    {
                        $arrAnsprechpartner = $stm->row();
                    }
                }

                if ($arrAnsprechpartner === null || !is_array($arrAnsprechpartner) || !isset($arrAnsprechpartner['id']))
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
                    "nk"              => $this->formatNumber2($value['charges']),
                    "hk"              => $this->formatNumber2($value['heating']),
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
                    "everbrauchswert" => $this->formatNumber2($value['flat_enev_verbrauchswert']),
                    "ebedarfswert"    => $this->formatNumber2($value['flat_enev_ebedarfswert']),
                    "eheizung"        => $value['flat_lights'] ? $value['flat_lights'] : '',
                    "ausstattung"     => join(', ', $environment)
                );
                $stm = \Database::getInstance()->prepare("INSERT INTO is_details %s")->set($set)->execute();

                if ($stm->affectedRows)
                {
                    echo sprintf('Import von "%s, %s %s, %s %s"', $set['title'], $set['strasse'], $set['hnr'], $set['plz'], $set['ort']) . "<br>";
                    $wid = $stm->insertId;
                    $set = array(
                        "wid"         => $wid,
                        "gid"         => $arr_wohngebiete[$value['district_name']],
                        "aid"         => $arrAnsprechpartner['id'] ? $arrAnsprechpartner['id'] : 1,
                        "zimmer"      => $value['rooms'],
                        "flaeche"     => $this->formatNumber2($value['space']),
                        //"warm"        => $this->formatNumber($value['rent_all']),
                        "warm"        => $this->formatNumber2($value['rent_all']),
                        //"kalt"        => $this->formatNumber($value['rent']),
                        "kalt"        => $this->formatNumber2($value['rent']),
                        "etage"       => preg_replace("/\.Etage/", "", $value['floor']),
                        "kaution"     => $this->formatNumber2($value['flat_deposit']),
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
                    \Database::getInstance()->prepare("INSERT INTO is_wohnungen %s")->set($set)->execute();
                }
            }

            echo "\n" . count($data['flats']) . " Wohnungen importiert\n";
            \System::log(count($data['flats']) . " Wohnungen importiert", __METHOD__, TL_GENERAL);
        }
        else
        {
            echo "\nKeine Wohnungen importiert.\n";
        }

        echo "\n" . sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime) . "\n";
        \System::log(sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime), __METHOD__, TL_GENERAL);
        echo '</pre>';
        exit();
    }

    /**
     * @param $strTable
     * @param $arrangerNr
     * @param $arrValue
     */
    protected function updateTableAnsprechpartner($arrangerNr, array $arrValue)
    {
        // Insert new arranger
        $stm = \Database::getInstance()->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")->limit(1)->execute($arrangerNr);
        if (!$stm->numRows)
        {
            $set = array(
                'arrangernr' => $arrangerNr
            );
            $objInsertStmt = \Database::getInstance()->prepare("INSERT INTO is_ansprechpartner %s")->set($set)->execute();
            if ($objInsertStmt->affectedRows)
            {
                echo "INSERT new record INTO is_ansprechpartner ID " . $objInsertStmt->insertId . "<br>";
            }
        }

        $stm = \Database::getInstance()->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")->limit(1)->execute($arrangerNr);
        if ($stm->numRows)
        {
            // Update arranger
            $arrName = explode(' ', $arrValue['arranger_name']);
            $set = array(
                'anrede'  => $arrName[0],
                'vorname' => $arrName[1],
                'name'    => $arrName[2],
                'email'   => $arrValue['arranger_email'],
                'tel'     => $arrValue['arranger_phone'],
                //'mobile'  => $value['arranger_phone'], // There is no mobile number submitted?
                'fax'     => $arrValue['arranger_fax']
            );
            if (!count($arrName) === 3)
            {
                $set['anrede'] = '';
                $set['vorname'] = '';
                $set['name'] = $arrValue['arranger_name'];
            }

            $objInsertStmt = \Database::getInstance()->prepare("UPDATE is_ansprechpartner %s WHERE id=?")->set($set)->execute($stm->id);
            if ($objInsertStmt->affectedRows)
            {
                echo "UPDATE is_ansprechpartner WHERE id=" . $stm->id . "<br>";
            }
        }
    }

    /**
     *
     */
    protected function extendTables()
    {
        // Add columns is_wohnungen.flat_id & is_wohnungen.gallery_img
        if (!\Database::getInstance()->fieldExists('flat_id', 'is_wohnungen'))
        {
            \Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `flat_id` INT NOT NULL');
        }

        if (!\Database::getInstance()->fieldExists('gallery_img', 'is_wohnungen'))
        {
            \Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `gallery_img` BLOB NOT NULL');
        }

        // Add is_ansprechpartner.arrangernr (extended on 27.11.2019, Marko Cupic)
        if (!\Database::getInstance()->fieldExists('arrangernr', 'is_ansprechpartner'))
        {
            echo 'Added field is_ansprechpartner.arrangernr' . '<br>';
            \Database::getInstance()->query('ALTER TABLE `is_ansprechpartner` ADD `arrangernr` INT NOT NULL');
        }
    }

    /**
     * @param $curloptFile
     * @param $curloptUrl
     * @param int $curloptTimeout
     */
    private function curlFileDownload($curloptFile, $curloptUrl, $curloptTimeout = 30)
    {
        //Open file handler.
        $fp = fopen($curloptFile, 'w+');

        //If $fp is FALSE, something went wrong.
        if ($fp === false)
        {
            echo sprintf("Konnte Datei: %s nicht öffnen.", $curloptFile) . "\n";
            return;
        }

        //Create a cURL handle.
        $ch = curl_init($curloptUrl);

        //Pass our file handle to cURL.
        curl_setopt($ch, CURLOPT_FILE, $fp);

        //Timeout if the file doesn't download after 20 seconds.
        curl_setopt($ch, CURLOPT_TIMEOUT, $curloptTimeout);

        //Execute the request.
        curl_exec($ch);

        //If there was an error, throw an Exception
        if (curl_errno($ch))
        {
            echo sprintf("Es ist ein Fehler passiert. Konnte Datei: %s nicht öffnen.", $curloptFile) . "\n";
            return;
        }

        //Get the HTTP status code.
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //Close the cURL handler.
        curl_close($ch);

        //Close the file handler.
        fclose($fp);

        if ($statusCode == 200)
        {
            //echo 'Downloaded!';
        }
        else
        {
            echo "Download-Fehler. Status Code: " . $statusCode . "\n";
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

    /**
     * @param $number
     * @return float
     */
    private function formatNumber2($number)
    {
        $number = (string)$number;
        $number = preg_replace("/\./", "", $number);
        return $number;
    }

    /**
     * @param $strArray
     * @param bool $blnForce
     * @return array|null|string
     */
    private static function deserialize($strArray, $blnForce = false)
    {
        if (version_compare(VERSION, 4.0, '<'))
        {
            return deserialize($strArray, $blnForce);
        }
        else
        {
            return \StringUtil::deserialize($strArray, $blnForce);
        }
    }
}

