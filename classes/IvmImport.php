<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 19.03.2019
 * Time: 07:56
 */

namespace Markocupic\Ivm;

use Contao\Config;
use Contao\Database;
use Contao\File;
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
    protected $downloadFolder = 'files/Wohnungsangebote';

    /**
     * import database from https://wg-dessau.ivm-professional.de
     */
    public function importDatabase()
    {
        if (strlen(Input::get('ivmImport')))
        {
            echo '<pre>';
            echo "Starte den Importvorgang...\n\n";

            $startTime = time();
            set_time_limit(0);
            error_reporting(E_ALL);

            if (strlen(Input::get('force')))
            {
                $this->force = true;
            }

            // Create Folder and unprotect it
            $objDownloadFolder = new Folder($this->downloadFolder, true);
            if (version_compare(VERSION, '3.5', '>'))
            {
                if (!file_exists(TL_ROOT . '/' . $this->downloadFolder . '/.public'))
                {
                    echo "Mache Download-Ordner " . $this->downloadFolder . " öffentlich...\n\n";
                    \File::putContent($this->downloadFolder . '/.public', '');
                    $fp = fopen(TL_ROOT . '/' . $this->downloadFolder . '/.public', 'w');
                    fwrite($fp, 'Erstellt durch ' . __METHOD__);
                    fclose($fp);
                }
            }

            //$objDownloadFolder->unprotect();

            // Purge download folder
            if ($this->force)
            {
                $objDownloadFolder->purge();
            }

            // Folder settings
            define('JSON_IVM_URL', 'https://wg-dessau.ivm-professional.de');
            define('IMAGE_PATH', TL_ROOT . '/' . $this->downloadFolder . '/');

            // Database settings
            $mysql_server = sprintf("mysql:host=%s;dbname=%s", Config::get('dbHost'), Config::get('dbDatabase'));
            $mysql_user = Config::get('dbUser');
            $mysql_pass = Config::get('dbPass');

            // Let's start the import process...

            echo "Baue Verbindung zur Datenbank auf...\n\n";
            $db = new \PDO($mysql_server, $mysql_user, $mysql_pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Truncate tables
            $db->query('TRUNCATE TABLE is_details');
            $db->query('TRUNCATE TABLE is_wohnungen');
            $db->query('TRUNCATE TABLE is_wohngebiete');
            //$db->query('TRUNCATE TABLE is_ansprechpartner');

            // Add column
            if (!Database::getInstance()->fieldExists('flat_id', 'is_wohnungen'))
            {
                Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `flat_id` INT NOT NULL');
            }

            if (!Database::getInstance()->fieldExists('gallery_img', 'is_wohnungen'))
            {
                Database::getInstance()->query('ALTER TABLE `is_wohnungen` ADD `gallery_img` VARCHAR(2048) NOT NULL');
            }

            // Get Ausstattungen
            $data_raw = file_get_contents(JSON_IVM_URL . "/modules/json/json_environments.php");
            $ausstattungen = StringUtil::deserialize(json_decode($data_raw), true);
            echo count($ausstattungen) . " Ausstattungen geladen\n\n";

            // Import Wohngebiete
            $data_raw = file_get_contents(JSON_IVM_URL . "/modules/json/json_districts.php");
            $data = StringUtil::deserialize(json_decode($data_raw), true);

            $arr_wohngebiete = array();
            echo "Importiere Wohngebiete...\n";
            foreach ($data as $key => $value)
            {
                $stm = $db->prepare("INSERT INTO is_wohngebiete (id,wohngebiet) VALUES(:id, :name)");
                $stm->execute(array(
                    ":id"   => $key,
                    ":name" => $value['name']
                ));

                $arr_wohngebiete[$value['name']] = $db->lastInsertId();
            }
            echo count($arr_wohngebiete) . " Wohngebiete geladen.\n\n";

            // Get Top-Wohnungen
            echo "Importiere Top-Wohnungen...\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, JSON_IVM_URL . "/modules/json/json_search.php");
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
            curl_setopt($ch, CURLOPT_URL, JSON_IVM_URL . "/modules/json/json_search.php");
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
                        CURLOPT_URL            => JSON_IVM_URL . "/modules/json/json_details.php",
                    ));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    //!!!!!!!!!!!!!!!! Get gallery images
                    $objDetails = json_decode($response);
                    $gallery_img = urldecode($objDetails->gallery_img);
                    echo "Importiere Galerie: " . $gallery_img . "...\n";

                    $environment = StringUtil::deserialize(urldecode($value['environmet']), true);

                    if ($this->force || !file_exists(IMAGE_PATH . $value['image']))
                    {
                        echo "Lade Bild " . $value['image'] . "\n";
                        $ch = curl_init();
                        curl_setopt_array($ch, array(
                            CURLOPT_FILE    => fopen(IMAGE_PATH . $value['image'], 'w'),
                            CURLOPT_TIMEOUT => 60 * 10,
                            CURLOPT_URL     => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/flats/' . urlencode($value['image']) . '&w=1024&h=1024',
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                    }
                    $pics[] = $value['image'];

                    if ($value['flat_plot'])
                    {
                        if ($this->force || !file_exists(IMAGE_PATH . $value['flat_plot']))
                        {
                            echo "Lade Grundriss " . $value['flat_plot'] . "\n";
                            $ch = curl_init();
                            curl_setopt_array($ch, array(
                                CURLOPT_FILE    => fopen(IMAGE_PATH . $value['flat_plot'], 'w'),
                                CURLOPT_TIMEOUT => 60 * 5,
                                CURLOPT_URL     => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot']) . '&w=1024',
                            ));
                            curl_exec($ch);
                            curl_close($ch);
                        }
                        $pics[] = $value['flat_plot'];
                    }
                    if ($value['flat_plot2'])
                    {
                        if ($this->force || !file_exists(IMAGE_PATH . $value['flat_plot']))
                        {
                            echo "Lade Grundriss 2 " . $value['flat_plot2'] . "\n";
                            $ch = curl_init();
                            curl_setopt_array($ch, array(
                                CURLOPT_FILE    => fopen(IMAGE_PATH . $value['flat_plot2'], 'w'),
                                CURLOPT_TIMEOUT => 60 * 5,
                                CURLOPT_URL     => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot2']) . '&w=1024',
                            ));
                            curl_exec($ch);
                            curl_close($ch);
                        }
                        $pics[] = $value['flat_plot2'];
                    }

                    if ($this->force || !file_exists(IMAGE_PATH . 'expose_' . $value['flat_id'] . '.pdf'))
                    {
                        echo "Lade Expose " . $value['flat_pdf'] . "\n";
                        $ch = curl_init();
                        curl_setopt_array($ch, array(
                            CURLOPT_FILE    => fopen(IMAGE_PATH . 'expose_' . $value['flat_id'] . '.pdf', 'w'),
                            CURLOPT_TIMEOUT => 60 * 5,
                            CURLOPT_URL     => JSON_IVM_URL . '/make_pdf/make_pdf.php?flat_id=' . $value['flat_id'],
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                    }

                    if ($value['arranger'] && $value['arranger_email'])
                    {
                        $stm = $db->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%" . $value['arranger'] . "%' OR email LIKE '%" . $value['arranger_email'] . "%'");
                        $stm->execute();
                        $ansprechpartner = $stm->fetch(\PDO::FETCH_ASSOC);
                    }
                    else
                    {
                        $stm = $db->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%Wohnungsgenossenschaft%'");
                        $stm->execute();
                        $ansprechpartner = $stm->fetch(\PDO::FETCH_ASSOC);
                    }

                    if (!$ansprechpartner || !$ansprechpartner['id'])
                    {
                        echo "Kein Ansprechpartner für " . $value['arranger'] . ' ' . $value['arranger_email'] . "<br>";
                    }

                    // Prepare some fields
                    $value['objectdescription'] = join("\n", $environment) . "\n" . $value['objectdescription'];
                    $value['objectdescription'] .= "\n" . $value['note'];

                    $stm = $db->prepare("INSERT INTO is_details (
                        title,strasse,hnr,plz,ort,nk,hk,hk_in,beschr,beschr_lage,sonstige,typ,objektnr,baujahr,pics,fern,gas,
                        fenster,offen,fliesen,kunststoff,parkett,teppich,laminat,dielen,etage_heizung,zentral,keller,verfuegbar,
                        barrierefrei,expose,eausweis,everbrauchswert,ebedarfswert,eheizung,ausstattung,wg
                        ) VALUES(
                        :title,:strasse,:hnr,:plz,:ort,:nk,:hk,:hk_in,:beschr,:beschr_lage,:sonstige,:typ,:objektnr,:baujahr,:pics,:fern,:gas,
                        :fenster,:offen,:fliesen,:kunststoff,:parkett,:teppich,:laminat,:dielen,:etage_heizung,:zentral,:keller,:verfuegbar,
                        :barrierefrei,:expose,:eausweis,:everbrauchswert,:ebedarfswert,:eheizung,:ausstattung,:wg
                      )
                    ");

                    if (!$stm->execute(array(
                        ":title"           => $value['flat_exposetitle'],
                        ":strasse"         => $value['street'],
                        ":hnr"             => $value['streetnumber'] ? $value['streetnumber'] : '',
                        ":plz"             => $value['zip'],
                        ":ort"             => $value['city'],
                        ":nk"              => $this->formatNumber($value['charges']),
                        ":hk"              => $this->formatNumber($value['heating']),
                        //":hk_in" => $value['heating']==0 ? 'Ja' : 'Nein',
                        ":hk_in"           => 'Ja',
                        ":beschr"          => $value['objectdescription'],
                        ":beschr_lage"     => $value['district_description'],
                        ":sonstige"        => ($value['flat_note'] || $value['flat_special_text']) ? nl2br($value['flat_note'] . '<br>' . $value['flat_special_text']) : '',
                        ":typ"             => $value['portal_wohnungstyp'] && $value['portal_wohnungstyp'] != 'NO_INFORMATION' ? $value['portal_wohnungstyp'] : '',
                        ":objektnr"        => $value['flat_keynumber'],
                        ":baujahr"         => $value['flat_year'] ? $value['flat_year'] : '',
                        ":pics"            => join(';', $pics),
                        ":fern"            => preg_match('/fern/i', $value['flat_lights']) ? 'true' : '',
                        ":gas"             => preg_match('/gas/i', $value['flat_lights']) ? 'true' : '',
                        ":fenster"         => $environment[9] ? "true" : "",
                        ":offen"           => $environment[1] || $environment[43] ? "true" : "",
                        ":fliesen"         => '',
                        ":kunststoff"      => '',
                        ":parkett"         => '',
                        ":teppich"         => '',
                        ":laminat"         => '',
                        ":dielen"          => '',
                        ":etage_heizung"   => '',
                        ":zentral"         => '',
                        ":keller"          => '',
                        ":verfuegbar"      => '',
                        ":barrierefrei"    => $environment[17] ? "true" : "",
                        ":wg"              => '',
                        ":expose"          => 'expose_' . $value['flat_id'] . '.pdf',
                        ":eausweis"        => $value['flat_enev_ausweisart'] ? $value['flat_enev_ausweisart'] : '',
                        ":everbrauchswert" => $this->formatNumber($value['flat_enev_verbrauchswert']),
                        ":ebedarfswert"    => $this->formatNumber($value['flat_enev_ebedarfswert']),
                        ":eheizung"        => $value['flat_lights'] ? $value['flat_lights'] : '',
                        ":ausstattung"     => join(', ', $environment)
                    )))
                    {
                        print_r($stm->errorInfo());
                    };

                    $wid = $db->lastInsertId();
                    $stm = $db->prepare("INSERT INTO is_wohnungen (
                        wid,gid,aid,zimmer,flaeche,warm,kalt,etage,kaution,top,dusche,wanne,balkon,lift,garten,
                        ebk,flat_id,gallery_img
                        ) VALUES(
                        :wid,:gid,:aid,:zimmer,:flaeche,:warm,:kalt,:etage,:kaution,:top,:dusche,:wanne,:balkon,:lift,:garten,
                        :ebk,:flat_id,:gallery_img
                        )
                    ");
                    $stm->execute(array(
                        ":wid"         => $wid,
                        ":gid"         => $arr_wohngebiete[$value['district_name']],
                        ":aid"         => $ansprechpartner['id'] ? $ansprechpartner['id'] : 1,
                        ":zimmer"      => $value['rooms'],
                        ":flaeche"     => $this->formatNumber($value['space']),
                        ":warm"        => $this->formatNumber($value['rent_all']),
                        ":kalt"        => $this->formatNumber($value['rent']),
                        ":etage"       => preg_replace("/\.Etage/", "", $value['floor']),
                        ":kaution"     => $this->formatNumber($value['flat_deposit']),
                        ":dusche"      => $environment[7] ? "true" : "",
                        ":wanne"       => $environment[8] ? "true" : "",
                        ":balkon"      => $environment[14] ? "Balkon" : ($environment[16] ? "Terrasse" : ""),
                        ":lift"        => $environment[18] ? "true" : "",
                        ":garten"      => $environment[23] ? "true" : "",
                        ":ebk"         => $environment[3] ? "true" : "",
                        ":top"         => $top_wohnungen[$value['flat_id']] ? 1 : 0,
                        ":flat_id"     => $value['flat_id'],
                        ":gallery_img" => $gallery_img,
                    ));
                }

                echo "\n" . count($data['flats']) . " Wohnungen importiert\n";
                System::log(count($data['flats']) . " Wohnungen importiert", __METHOD__, TL_GENERAL);
            }
            else
            {
                echo "\nKeine Wohnungen importiert.\n";
            }

            echo "\n" . sprintf('Importprozess nach %s Sekunden beendet.', time() - $startTime) . "\n";
            System::log('Importprozess nach %s Sekunden beendet.', time() - $startTime, __METHOD__, TL_GENERAL);
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

