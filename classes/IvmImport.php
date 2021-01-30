<?php

declare(strict_types=1);

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
        $this->prtOpenBodyTag();

        $startTime = time();
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $this->page = $page >= 1 ? (int)$page : '';
        if ($this->page) {
            $this->prtScr(sprintf("Import Skript mit dem page=%s Parameter aufgerufen...", $this->page));
            $this->prtScr("");
        }

        $this->blnForce = $blnForce === true ? true : false;
        if ($this->blnForce) {
            $this->prtScr("Import Skript mit dem force=true Parameter aufgerufen...");
            $this->prtScr("");
        }

        $this->blnPurgeDownloadFolder = $blnPurgeDownloadFolder === true ? true : false;
        if ($this->blnPurgeDownloadFolder) {
            $this->prtScr("Import Skript mit dem blnPurgeDownloadFolder=true Parameter aufgerufen...");
            $this->prtScr("");
        }

        // Check tables. Add columns, if they does not exist.
        $this->checkTables();

        // Folder settings
        $this->imagePath = $projectDir.'/'.$this->downloadFolder;

        // Create download folder and unprotect it
        $objDownloadFolder = new Folder($this->downloadFolder);
        if (!file_exists($projectDir.'/'.$this->downloadFolder.'/.public')) {
            $this->prtScr("Erstelle Download-Ordner ".$this->downloadFolder." öffentlich.");
            $this->prtScr("");
            $fp = fopen($projectDir.'/'.$this->downloadFolder.'/.public', 'w');
            fwrite($fp, sprintf('Erstellt am %s durch %s Linie %s', Date::parse('Y.m.d'), __METHOD__, __LINE__));
            fclose($fp);
        }

        // Purge download folder
        if ($this->blnPurgeDownloadFolder && ($this->page === '' || $this->page === 1)) {
            $this->prtScr("Download Verzeichnis leeren ".$this->downloadFolder."...");
            $this->prtScr("");
            $objDownloadFolder->purge();
        }

        // Truncate tables
        if ($this->page === 1 || empty($this->page)) {
            $arrTables = array_keys(TableConfig::getTableData());
            foreach ($arrTables as $strTable) {
                $this->prtScr("Tabelle ".$strTable." wird geleert...");
                $this->prtScr("");
                Database::getInstance()->query('TRUNCATE TABLE '.$strTable);
            }
        }

        // Let's start the import process...
        $this->prtScr("Starte den Importvorgang...");
        $this->prtScr("");

        // Get Ausstattungen
        $data_raw = file_get_contents($this->jsonIvmUrl."/modules/json/json_environments.php");
        $ausstattungen = StringUtil::deserialize(json_decode($data_raw), true);
        $this->prtScr(count($ausstattungen)." Ausstattungen geladen.");
        $this->prtScr("");

        // Import Wohngebiete
        $data_raw = file_get_contents($this->jsonIvmUrl."/modules/json/json_districts.php");
        $data = StringUtil::deserialize(json_decode($data_raw), true);

        $arr_wohngebiete = [];
        $this->prtScr("Importiere Wohngebiete...");
        foreach ($data as $key => $value) {
            $set = [
                "id"         => $key,
                "wohngebiet" => (string)$value['name'],
            ];
            $stm = Database::getInstance()
                ->prepare("INSERT INTO is_wohngebiete %s")
                ->set($set)
                ->execute();
            if ($stm->affectedRows) {
                $arr_wohngebiete[$value['name']] = $stm->insertId;
            }
        }
        $this->prtScr(count($arr_wohngebiete)." Wohngebiete geladen.");
        $this->prtScr("");

        // Get Top-Wohnungen
        // $this->>log("Importiere Top-Wohnungen...");
        $curlOpt = [
            CURLOPT_URL        => $this->jsonIvmUrl."/modules/json/json_search.php",
            CURLOPT_POSTFIELDS => [
                'search_page' => 1,
                'tafel'       => 1,
            ],
        ];

        $top_wohnungen = [];
        $data = json_decode($this->getFromCurl($curlOpt), true);
        $this->prtScr(count($data)." Top-Wohnungen importiert.");
        $this->prtScr("");
        if (!empty($data['flats']) && is_array($data['flats'])) {
            foreach ($data['flats'] as $key => $value) {
                $top_wohnungen[$value['flat_id']] = true;
            }
        }

        // Import flats
        $arrCurlPost = ['tafel' => 0];
        if ($this->page != '') {
            $arrCurlPost['search_page'] = $this->page;
        }
        if (empty($this->page)) {
            $arrCurlPost['limit'] = 'all';
        }
        $curlOpt = [
            CURLOPT_URL        => $this->jsonIvmUrl."/modules/json/json_search.php",
            CURLOPT_POSTFIELDS => $arrCurlPost,
        ];

        $data = json_decode($this->getFromCurl($curlOpt), true);

        if (is_array($data['flats'])) {
            // $this->prtScr(count($data['flats']) . " Wohnungsangebote werden importiert.");

            foreach ($data['flats'] as $key => $value) {
                // $this->>log("Importiere Angebot " . $value['flat_id']");
                $pics = [];

                // Get Details
                $curlOpt = [
                    CURLOPT_POSTFIELDS => ['flat_id' => $value['flat_id']],
                    CURLOPT_URL        => $this->jsonIvmUrl."/modules/json/json_details.php",
                ];

                // Get gallery images
                $objDetails = json_decode($this->getFromCurl($curlOpt));
                $gallery_img = urldecode($objDetails->gallery_img);
                // $this->prtScr("Importiere Galerie: " . $gallery_img . "...");

                // $value['environmet'] this is a typo made by IVM-Professional ;-)
                $environment = StringUtil::deserialize(urldecode($value['environmet']), true);
                if (isset($value['image']) && !empty($value['image'])) {
                    if ($this->blnForce || !file_exists($this->imagePath.'/'.$value['image'])) {

                        if (strlen((string)$value['image'])) {
                            $this->prtScr("Lade Bild ".$value['image']);
                            $curloptUrl = $this->jsonIvmUrl.'/_lib/phpthumb/phpThumb.php?src=/_img/flats/'.urlencode($value['image']).'&w=1024&h=1024';
                            $curloptFile = $this->imagePath.'/'.$value['image'];
                            $this->curlFileDownload($curloptFile, $curloptUrl, 600);
                        }
                    }

                    $pics[] = $value['image'];
                }

                // Get flat plot
                if (isset($value['flat_plot']) && !empty($value['flat_plot'])) {
                    if ($this->blnForce || !file_exists($this->imagePath.'/'.$value['flat_plot'])) {
                        if (strlen((string)$value['flat_plot'])) {
                            $this->prtScr("Lade Grundriss ".$value['flat_plot']);
                            $curloptUrl = $this->jsonIvmUrl.'/_lib/phpthumb/phpThumb.php?src=/_img/plots/'.urlencode($value['flat_plot']).'&w=1024';
                            $curloptFile = $this->imagePath.'/'.$value['flat_plot'];
                            $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                        }
                    }
                    $pics[] = $value['flat_plot'];
                }

                // Get flat plot 2
                if (isset($value['flat_plot2']) && !empty($value['flat_plot2'])) {
                    if ($this->blnForce || !file_exists($this->imagePath.'/'.$value['flat_plot2'])) {
                        if (strlen((string)$value['flat_plot2'])) {
                            $this->prtScr("Lade Grundriss 2 ".$value['flat_plot2']);
                            $curloptUrl = $this->jsonIvmUrl.'/_lib/phpthumb/phpThumb.php?src=/_img/plots/'.urlencode($value['flat_plot2']).'&w=1024';
                            $curloptFile = $this->imagePath.'/'.$value['flat_plot2'];
                            $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                        }
                    }
                    $pics[] = $value['flat_plot2'];
                }

                if ($this->blnForce || !file_exists($this->imagePath.'/'.'expose_'.$value['flat_id'].'.pdf')) {
                    $this->prtScr("Lade Expose expose_".$value['flat_id'].".pdf");
                    $curloptUrl = $this->jsonIvmUrl.'/make_pdf/make_pdf.php?flat_id='.$value['flat_id'];
                    $curloptFile = $this->imagePath.'/'.'expose_'.$value['flat_id'].'.pdf';
                    $this->curlFileDownload($curloptFile, $curloptUrl, 300);
                }

                // Update table is_ansprechpartner
                if ($value['arrangernr']) {
                    $this->updateTableAnsprechpartner($value['arrangernr'], $value);
                }

                // Ansprechpartner
                $arrAnsprechpartner = null;
                if ($value['arrangernr']) {
                    $stm = Database::getInstance()
                        ->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")
                        ->limit(1)
                        ->execute($value['arrangernr']);
                    if ($stm->numRows) {
                        $arrAnsprechpartner = $stm->row();
                    }
                }

                if ($arrAnsprechpartner === null || !is_array($arrAnsprechpartner) || !isset($arrAnsprechpartner['id'])) {
                    $this->prtScr("Kein Ansprechpartner für ".$value['arranger'].' '.$value['arranger_email']);
                }

                // Prepare some fields
                $value['objectdescription'] = join("\n", $environment)."\n".$value['objectdescription'];
                $value['objectdescription'] .= "\n".$value['note'];

                $set = [
                    "title"           => $value['flat_exposetitle'],
                    "strasse"         => $value['street'],
                    "hnr"             => $value['streetnumber'] ? $value['streetnumber'] : '',
                    "plz"             => $value['zip'],
                    "ort"             => $value['city'],
                    "nk"              => $this->formatNumber($value['charges']),
                    "hk"              => $this->formatNumber($value['heating']),
                    "hk_in"           => 'Ja',
                    "beschr"          => $value['objectdescription'],
                    "beschr_lage"     => $value['district_description'],
                    "sonstige"        => ($value['flat_note'] || $value['flat_special_text']) ? nl2br($value['flat_note'].'<br>'.$value['flat_special_text']) : '',
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
                    "expose"          => 'expose_'.$value['flat_id'].'.pdf',
                    "eausweis"        => $value['flat_enev_ausweisart'] ? $value['flat_enev_ausweisart'] : '',
                    "everbrauchswert" => $this->formatNumber($value['flat_enev_verbrauchswert']),
                    "ebedarfswert"    => $this->formatNumber($value['flat_enev_ebedarfswert']),
                    "eheizung"        => $value['flat_lights'] ? $value['flat_lights'] : '',
                    "ausstattung"     => join(', ', $environment),
                    "flat_video_link" => strlen((string)$value['flat_video_link']) ? str_replace('embed=', '', (string)$value['flat_video_link']) : '',
                ];

                $set = array_map(
                    function ($v) {
                        return (string)$v;
                    },
                    $set
                );

                $stm = Database::getInstance()
                    ->prepare("INSERT INTO is_details %s")
                    ->set($set)
                    ->execute();
                if ($stm->affectedRows) {
                    $this->prtScr(sprintf('Import von "%s, %s %s, %s %s"', $set['title'], $set['strasse'], $set['hnr'], $set['plz'], $set['ort']));
                    $this->prtScr("");
                    $wid = $stm->insertId;
                    $set = [
                        "wid"         => $wid,
                        "gid"         => $arr_wohngebiete[$value['district_name']],
                        "aid"         => $arrAnsprechpartner['id'] ? $arrAnsprechpartner['id'] : 1,
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
                    ];

                    $set = array_map(
                        function ($v) {
                            return (string)$v;
                        },
                        $set
                    );

                    Database::getInstance()
                        ->prepare("INSERT INTO is_wohnungen %s")
                        ->set($set)
                        ->execute();
                }

            }
            $this->prtScr("");
            $this->prtScr(count($data['flats'])." Wohnungen importiert");
            $this->prtScr("");
            System::log(count($data['flats'])." Wohnungen importiert", __METHOD__, TL_GENERAL);
        } else {
            $this->prtScr("");
            $this->prtScr("Keine Wohnungen importiert.");
            $this->prtScr("");
        }
        $this->prtScr('');
        $this->prtScr(sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime));
        System::log(sprintf('IVM-Importprozess nach %s Sekunden beendet.', time() - $startTime), __METHOD__, TL_GENERAL);
        $this->prtCloseBodyTag();

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
        $stm = Database::getInstance()
            ->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")
            ->limit(1)
            ->execute($arrangerNr);
        if (!$stm->numRows) {
            $set = [
                'arrangernr' => (string)$arrangerNr,
            ];
            $objInsertStmt = Database::getInstance()
                ->prepare("INSERT INTO is_ansprechpartner %s")
                ->set($set)
                ->execute();
            if ($objInsertStmt->affectedRows) {
                $this->prtScr("INSERT new record INTO is_ansprechpartner ID ".$objInsertStmt->insertId.".");
            }
        }

        $stm = Database::getInstance()
            ->prepare("SELECT * FROM is_ansprechpartner WHERE arrangernr=?")
            ->limit(1)
            ->execute($arrangerNr);
        if ($stm->numRows) {
            // Update arranger
            $arrName = explode(' ', $arrValue['arranger_name']);
            $set = [
                'anrede'  => $arrName[0],
                'vorname' => $arrName[1],
                'name'    => $arrName[2],
                'email'   => $arrValue['arranger_email'],
                'tel'     => $arrValue['arranger_phone'],
                // 'mobile'  => $value['arranger_phone'], // There is no mobile number submitted?
                'fax'     => $arrValue['arranger_fax'],
            ];
            if (!count($arrName) === 3) {
                $set['anrede'] = '';
                $set['vorname'] = '';
                $set['name'] = $arrValue['arranger_name'];
            }

            $objInsertStmt = Database::getInstance()
                ->prepare("UPDATE is_ansprechpartner %s WHERE id=?")
                ->set($set)
                ->execute($stm->id);
            if ($objInsertStmt->affectedRows) {
                $this->prtScr("UPDATE is_ansprechpartner WHERE id=".$stm->id);
            }
        }
    }

    /**
     * @param $curloptFile
     * @param $curloptUrl
     * @param int $curloptTimeout
     */
    private function curlFileDownload($curloptFile, $curloptUrl, $curloptTimeout = 30)
    {
        // Open file handler.
        $fp = fopen($curloptFile, 'w+');

        if ($fp === false) {
            $this->prtScr(sprintf("Konnte Datei: %s nicht öffnen.", $curloptFile));

            return;
        }

        // Create a cURL handle.
        $ch = curl_init($curloptUrl);

        // Pass our file handle to cURL.
        curl_setopt($ch, CURLOPT_FILE, $fp);

        // Timeout if the file doesn't download after 20 seconds.
        curl_setopt($ch, CURLOPT_TIMEOUT, $curloptTimeout);

        // Execute the request.
        curl_exec($ch);

        //If there was an error, throw an Exception
        if (curl_errno($ch)) {
            $this->prtScr(sprintf("Es ist ein Fehler passiert. Konnte Datei: %s nicht öffnen.", $curloptFile));

            return;
        }

        // Get the HTTP status code.
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL handler.
        curl_close($ch);

        // Close the file handler.
        fclose($fp);

        if ($statusCode === 200) {
            // $this->prtScr("Downloaded!");
        } else {
            $this->prtScr("Download-Fehler. Status Code: ".$statusCode);
        }
    }

    /**
     * @param $number
     * @return string
     */
    private function formatNumber($number): string
    {
        $number = (string)$number;
        $number = preg_replace("/\./", "", $number);

        return $number;
    }

    private function getFromCurl(array $arrOptions = null): string
    {
        $ch = curl_init();

        curl_setopt_array(
            $ch,
            [
                CURLOPT_HEADER         => 0,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => [],
                CURLOPT_TIMEOUT        => 300,
                CURLOPT_URL            => null,
            ]
        );

        foreach ($arrOptions as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        $response = curl_exec($ch);

        //If there was an error, throw an Exception
        if (curl_errno($ch)) {
            throw new \Exception('CURL error!');
        }
        curl_close($ch);

        return $response;
    }

    private function checkTables()
    {
        $arrTables = array_keys(TableConfig::getTableData());
        $arrTableConfig = TableConfig::getTableData();

        foreach ($arrTables as $strTable) {
            foreach ($arrTableConfig[$strTable] as $type => $fields) {
                foreach ($fields as $columnname) {
                    $this->prtScr("Check field exists ".$strTable.".".$columnname);
                    if (!Database::getInstance()->fieldExists($columnname, $strTable)) {
                        $query = sprintf(
                            'ALTER TABLE %s ADD COLUMN %s %s',
                            $strTable,
                            $columnname,
                            $type
                        );
                        Database::getInstance()->query($query);
                        $this->prtScr($query);
                    } else {
                        $this->prtScr('...ok!');
                    }
                }
            }
        }
    }

    private function prtScr(string $strLog)
    {
        if (empty(trim((string)$strLog))) {
            $strLog = "...";
        }
        echo "<pre>".$strLog."</pre>";
    }

    private function prtOpenBodyTag()
    {
        echo '<html><body style="background-color:black; color: #06e706; padding:10px">';
    }

    private function prtCloseBodyTag()
    {
        echo '</body></html>';
    }
}

