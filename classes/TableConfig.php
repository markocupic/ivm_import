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

class TableConfig
{
    public static function getTableData(): array
    {
        return [
            // Table is_wohnungen
            'is_wohnungen'       => [
                "int(22) unsigned NOT NULL auto_increment" => [
                    "id",
                ],
                "int(11) NOT NULL"                         => [
                    "wid",
                    "gid",
                    "aid",
                    "wid",
                    "flat_id",
                    "flaeche",
                ],
                "tinyint(1) NOT NULL"                      => [
                    "top",
                ],
                "varchar(255) NOT NULL default ''"         => [
                    "etage",
                    "dusche",
                    "wanne",
                    "balkon",
                    "lift",
                    "garten",
                    "ebk",
                ],
                "float"                                    => [
                    "zimmer",
                    "baeder",
                    "warm",
                    "kalt",
                    "kaution",
                ],
                "blob NULL"                                => [
                    "gallery_img",
                ],
            ],
            // Table is_wohngebiete
            'is_wohngebiete'     => [
                "int(22) unsigned NOT NULL auto_increment" => [
                    "id",
                ],
                "varchar(255) NOT NULL default ''"         => [
                    "wohngebiet",
                ],
            ],
            // Table is_ansprechpartner
            'is_ansprechpartner' => [
                "int(22) unsigned NOT NULL auto_increment" => [
                    "id",
                ],
                "int(11) NOT NULL"                         => [
                    "arrangernr",
                ],
                "varchar(255) NOT NULL default ''"         => [
                    "anrede",
                    "vorname",
                    "name",
                    "email",
                    "tel",
                    "fax",
                    "mobile",
                ],
            ],
            // Table is_details
            'is_details'         => [
                "int(22) unsigned NOT NULL auto_increment" => [
                    "id",
                ],
                "varchar(255) NOT NULL default ''"         => [
                    "title",
                    "strasse",
                    "hnr",
                    "plz",
                    "ort",
                    "hk_in",
                    "typ",
                    "fenster",
                    "offen",
                    "fliesen",
                    "kunststoff",
                    "parkett",
                    "teppich",
                    "laminat",
                    "dielen",
                    "stein",
                    "estrich",
                    "doppelboden",
                    "fern",
                    "etage_heizung",
                    "zentral",
                    "gas",
                    "oel",
                    "keller",
                    "baujahr",
                    "zustand",
                    "verfuegbar",
                    "barrierefrei",
                    "wg",
                    "objektnr",
                    "eausweis",
                    "eheizung",
                    "haustiere",
                    "moebliert",
                    "rollstuhlgerecht",
                    "raeume_veraenderbar",
                    "wbs_sozialwohnung",
                    "e_typ",
                    "e_wert",
                    "gen_anteile",
                    "anz_schlafzimmer",
                    "neubau",
                    "altbau",
                    "reinigung",
                    "senioren",
                    "flat_video_link",
                ],
                "text NULL"                                => [
                    "pics",
                    "expose",
                    "energie",
                    "beschr",
                    "beschr_lage",
                    "sonstige",
                    "ausstattung",
                ],
                "float"                                    => [
                    "nk",
                    "hk",
                    "stellplatz",
                    "everbrauchswert",
                    "ebedarfswert",
                ],
            ],
        ];
    }
}