<?php
set_time_limit(0);
error_reporting(E_ALL);

// Settings
define('JSON_IVM_URL', 'https://wg-dessau.ivm-professional.de');
define('IMAGE_PATH', './Wohnungsangebote/');

// neuer Server
$mysql_server = "mysql:host=localhost;dbname=usr_web26862749_34";
$mysql_user = "web26862749";
$mysql_pass = "pideagmbh";


// Live-Server
//$mysql_server = "mysql:host=localhost;dbname=usr_web26862749_34";
//$mysql_user = "db501111u1";
//$mysql_pass = "b!hgHzk?8P+C";

// DEV-Server
//$mysql_server = "mysql:host=localhost;dbname=usr_web26862749_43";
//$mysql_user = "web26862749";
//$mysql_pass = "pideagmbh";

// Localhost
//$mysql_server = "mysql:host=localhost;dbname=wgdessau";
//$mysql_user = "root";
//$mysql_pass = "x864lk";
//$_GET["force"] = 1;

echo '<pre>';

echo "Baue Verbindung zur Datenbank auf...\n";
$db = new PDO($mysql_server, $mysql_user, $mysql_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!($_GET['page'] > 1)) {
    echo "Leere Tabellen...\n";
    $result = $db->query('TRUNCATE TABLE is_details');
    $result = $db->query('TRUNCATE TABLE is_wohnungen');
}
$result = $db->query('TRUNCATE TABLE is_wohngebiete');
//$result = $db->query('TRUNCATE TABLE is_ansprechpartner');

// Hole Ausstattungen
$data_raw = file_get_contents(JSON_IVM_URL . "/modules/json/json_environments.php");
$ausstattungen = unserialize(json_decode($data_raw));
echo count($ausstattungen) . " Ausstattungen geladen\n";

// Importiere Wohngebiete
$data_raw = file_get_contents(JSON_IVM_URL . "/modules/json/json_districts.php");
$data = unserialize(json_decode($data_raw));

$arr_wohngebiete = array();

echo "Importiere Wohngebiete...\n";
foreach ($data as $key => $value) {
    $stm = $db->prepare("INSERT INTO is_wohngebiete (id,wohngebiet) VALUES(:id, :name)");
    $stm->execute(array(
        ":id" => $key,
        ":name" => $value['name']
    ));

    $arr_wohngebiete[$value['name']] = $db->lastInsertId();
}
echo count($arr_wohngebiete) . " Wohngebiete geladen\n";

// Hole Top-Wohnungen
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, JSON_IVM_URL . "/modules/json/json_search.php");
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_POSTFIELDS, array(
    'search_page' => 1,
    'tafel' => 1
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);


$top_wohnungen = array();
$data = json_decode($response, true);
foreach ($data['flats'] as $key => $value) {
    $top_wohnungen[$value['flat_id']] = true;
}

// Importiere Wohnungen
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, JSON_IVM_URL . "/modules/json/json_search.php");
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_POSTFIELDS, array(
    'search_page' => $_GET['page'] ? $_GET['page'] : 1,
    'tafel' => 0
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (is_array($data['flats'])) {

    echo count($data['flats']) . " Wohnungsangebote werden importiert\n";

    foreach ($data['flats'] as $key => $value) {

        echo "Importiere Angebot {$value['flat_id']}\n";
        $pics = array();

        // Hole Details
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => array('flat_id' => $value['flat_id']),
            CURLOPT_TIMEOUT => 60 * 5,
            CURLOPT_URL => JSON_IVM_URL . "/modules/json/json_details.php",
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        $details = json_decode($response, true);

        $environment = array();
        $environment = unserialize(urldecode($value['environmet']));

        if ($_GET['force'] || !file_exists(IMAGE_PATH . $value['image'])) {
            echo "Lade Bild " . $value['image'] . "\n";
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_FILE => fopen(IMAGE_PATH . $value['image'], 'w'),
                CURLOPT_TIMEOUT => 60 * 10,
                CURLOPT_URL => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/flats/' . urlencode($value['image']) . '&w=1024&h=1024',
            ));
            curl_exec($ch);
            curl_close($ch);
        }
        $pics[] = $value['image'];

        if ($value['flat_plot']) {
            if ($_GET['force'] || !file_exists(IMAGE_PATH . $value['flat_plot'])) {
                echo "Lade Grundriss " . $value['flat_plot'] . "\n";
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_FILE => fopen(IMAGE_PATH . $value['flat_plot'], 'w'),
                    CURLOPT_TIMEOUT => 60 * 5,
                    CURLOPT_URL => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot']) . '&w=1024',
                ));
                curl_exec($ch);
                curl_close($ch);
            }
            $pics[] = $value['flat_plot'];
        }
        if ($value['flat_plot2']) {
            if ($_GET['force'] || !file_exists(IMAGE_PATH . $value['flat_plot'])) {
                echo "Lade Grundriss 2 " . $value['flat_plot2'] . "\n";
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_FILE => fopen(IMAGE_PATH . $value['flat_plot2'], 'w'),
                    CURLOPT_TIMEOUT => 60 * 5,
                    CURLOPT_URL => JSON_IVM_URL . '/_lib/phpthumb/phpThumb.php?src=/_img/plots/' . urlencode($value['flat_plot2']) . '&w=1024',
                ));
                curl_exec($ch);
                curl_close($ch);
            }
            $pics[] = $value['flat_plot2'];
        }

        if ($_GET['force'] || !file_exists(IMAGE_PATH . 'expose_' . $value['flat_id'] . '.pdf')) {
            echo "Lade Expose " . $value['flat_pdf'] . "\n";
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_FILE => fopen(IMAGE_PATH . 'expose_' . $value['flat_id'] . '.pdf', 'w'),
                CURLOPT_TIMEOUT => 60 * 5,
                CURLOPT_URL => JSON_IVM_URL . '/make_pdf/make_pdf.php?flat_id=' . $value['flat_id'],
            ));
            curl_exec($ch);
            curl_close($ch);
        }

        if ($value['arranger'] && $value['arranger_email']) {
            $stm = $db->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%" . $value['arranger'] . "%' OR email LIKE '%" . $value['arranger_email'] . "%'");
            $stm->execute();
            $ansprechpartner = $stm->fetch(PDO::FETCH_ASSOC);
        } else {
            $stm = $db->prepare("SELECT id FROM is_ansprechpartner WHERE name LIKE '%Wohnungsgenossenschaft%'");
            $stm->execute();
            $ansprechpartner = $stm->fetch(PDO::FETCH_ASSOC);
        }

        if (!$ansprechpartner || !$ansprechpartner['id']) {
            echo "Kein Ansprechpartner für " . $value['arranger'] . ' '. $value['arranger_email'] . "<br>";
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
            ":title" => $value['flat_exposetitle'],
            ":strasse" => $value['street'],
            ":hnr" => $value['streetnumber'] ? $value['streetnumber'] : '',
            ":plz" => $value['zip'],
            ":ort" => $value['city'],
            ":nk" => formatNumber($value['charges']),
            ":hk" => formatNumber($value['heating']),
            //":hk_in" => $value['heating']==0 ? 'Ja' : 'Nein',
            ":hk_in" => 'Ja',
            ":beschr" => $value['objectdescription'],
            ":beschr_lage" => $value['district_description'],
            ":sonstige" => ($value['flat_note'] || $value['flat_special_text']) ? nl2br($value['flat_note'] . '<br>' . $value['flat_special_text']) : '',
            ":typ" => $value['portal_wohnungstyp'] && $value['portal_wohnungstyp'] != 'NO_INFORMATION' ? $value['portal_wohnungstyp'] : '',
            ":objektnr" => $value['flat_keynumber'],
            ":baujahr" => $value['flat_year'] ? $value['flat_year'] : '',
            ":pics" => join(';', $pics),
            ":fern" => preg_match('/fern/i', $value['flat_lights']) ? 'true' : '',
            ":gas" => preg_match('/gas/i', $value['flat_lights']) ? 'true' : '',
            ":fenster" => $environment[9] ? "true" : "",
            ":offen" => $environment[1] || $environment[43] ? "true" : "",
            ":fliesen" => '',
            ":kunststoff" => '',
            ":parkett" => '',
            ":teppich" => '',
            ":laminat" => '',
            ":dielen" => '',
            ":etage_heizung" => '',
            ":zentral" => '',
            ":keller" => '',
            ":verfuegbar" => '',
            ":barrierefrei" => $environment[17] ? "true" : "",
            ":wg" => '',
            ":expose" => 'expose_' . $value['flat_id'] . '.pdf',
            ":eausweis" => $value['flat_enev_ausweisart'] ? $value['flat_enev_ausweisart'] : '',
            ":everbrauchswert" => formatNumber($value['flat_enev_verbrauchswert']),
            ":ebedarfswert" => formatNumber($value['flat_enev_ebedarfswert']),
            ":eheizung" => $value['flat_lights'] ? $value['flat_lights'] : '',
            ":ausstattung" => join(', ', $environment)
        ))
        ) {
            print_r($stm->errorInfo());
        };

        $wid = $db->lastInsertId();
        $stm = $db->prepare("INSERT INTO is_wohnungen (
                wid,gid,aid,zimmer,flaeche,warm,kalt,etage,kaution,top,dusche,wanne,balkon,lift,garten,
                ebk
                ) VALUES(
                :wid,:gid,:aid,:zimmer,:flaeche,:warm,:kalt,:etage,:kaution,:top,:dusche,:wanne,:balkon,:lift,:garten,
                :ebk
                )
        ");
        $stm->execute(array(
            ":wid" => $wid,
            ":gid" => $arr_wohngebiete[$value['district_name']],
            ":aid" => $ansprechpartner['id'] ? $ansprechpartner['id'] : 1,
            ":zimmer" => $value['rooms'],
            ":flaeche" => formatNumber($value['space']),
            ":warm" => formatNumber($value['rent_all']),
            ":kalt" => formatNumber($value['rent']),
            ":etage" => preg_replace("/\.Etage/", "", $value['floor']),
            ":kaution" => formatNumber($value['flat_deposit']),
            ":dusche" => $environment[7] ? "true" : "",
            ":wanne" => $environment[8] ? "true" : "",
            ":balkon" => $environment[14] ? "Balkon" : ($environment[16] ? "Terrasse" : ""),
            ":lift" => $environment[18] ? "true" : "",
            ":garten" => $environment[23] ? "true" : "",
            ":ebk" => $environment[3] ? "true" : "",
            ":top" => $top_wohnungen[$value['flat_id']] ? 1 : 0
        ));

    }

    echo count($data['flats']) . " Wohnungen importiert";
} else {
    echo "Keine Wohnungen importiert";
}

echo '</pre>';

function formatNumber($number)
{
    return doubleval(preg_replace("/,/", ".", $number));
}
