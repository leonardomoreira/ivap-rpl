<?php

class Actions
{
    var $file = NULL;
    var $iata = NULL;
   
    public function __construct($file)
    {
        $this->file = $file;
        $this->iata = substr($file, 12, 3);
    }
    
    public function init()
    {
        $stream  = fopen($this->file, "r");
        $rows = array();

        /* get txt lines */
        while (!feof ($stream))
        {
            $block = fgets($stream, 4096);

            if($block{0} == " " && !empty($block) && (is_numeric($block{5})) || $block{5} == " ")
            {       
                /* remove white spaces */
                $block = trim(implode(' ', preg_split('/\s+/', $block)));

                array_push($rows, $block);
            } 

        }

        /* remove header */
        unset($rows[0]);
        unset($rows[1]);

        /* transport flightplan to one line*/
        foreach($rows as $key => $item)
        {
            /* search eet and merge with line fpl */
            if(substr($item, 0, 3) == "EET")
            {
                $rows[$key-1] .= " ".$rows[$key];
                unset($rows[$key]);
            }

            /* search eet with route and explode */
            if(!is_numeric(substr($item, 0, 1)) && (substr($item, 0, 3) != "EET"))
            {        
                $position = strpos($rows[$key], "EET");

                if($position !== FALSE)
                {
                    $length = strlen($rows[$key]);
                    $eet    = substr($rows[$key], $position, ($length-$position));

                    /* merge with line fpl */
                    $rows[$key-1] .= " ".$eet;

                    /* remove eet this line */
                    $rows[$key] = str_replace($eet, NULL, $rows[$key]);
                }

                /* get EQPT position and insert rest of route before */
                $position = strpos($rows[$key-1], "EQPT");
                $position = ($position - 9);

                $part1 = substr($rows[$key-1], 0, $position);
                $part2 = str_replace($part1, NULL, $rows[$key-1]);

                $rows[$key-1] = $part1.$rows[$key].$part2;   

                unset($rows[$key]);
            }
            
            
        }

        fclose ($stream);

        return $rows;
    }
    
    public function flights($rows)
    {
        $flights = array();
        foreach($rows as $key => $item)
        {
            /* company details */
            $company = $this->company($this->iata);
                    
            /* flight plan length */
            $length = strlen($item);

            /* flight id */
            $flight = substr($item, 19, 7);
            
            /*aircraft */
            $aircraft = substr($item, 27, 4);
            
            /* aircraft category */
            $wakecat = substr($item, 32, 1);
            
            /* departure */
            $dep_icao = substr($item, 34, 4);
            $dep_time = substr($item, 38, 4);
            
            /* speed */
            $speed_type = substr($item, 43, 1);
            $speed = substr($item, 44, 4);
            
            /* flight level */
            $level = substr($item, 49, 3);
            
            /* remark */
            $remark = substr($rows[$key], strpos($item, "EQPT"), ($length-strpos($item, "EQPT")));
            //if($key > 2) $remark .= " FROM/".substr($rows[$key-1], 34, 4);
            $remark .= " REG/".$company[$aircraft]["REG"][rand(0, (count($company[$aircraft]["REG"])-1))]." OPR/".$company["OPR"]. " PER/".$company[$aircraft]["PER"];
           
            $remark = str_replace("EET/EET/", "EET/", $remark);
            
            /* remove data to get only destination and route */
            $item = str_replace(array(substr($item, 0, 53), substr($item, strpos($item, "EQPT"), ($length-strpos($item, "EQPT")))), NULL, $item);
            
            /* arrival */
            $arr_icao = substr($item, -9, 4);
            $eet = substr($item, -5);
                        
            /* remove to get route */
            $route = str_replace(array($eet, $arr_icao), NULL, $item);

            /* flight rules */
            $rules = "I";
            if(strpos($route, "/N") && strpos($route, "DCT")) $rules = "Z";
            if(strpos($route, "VFR")) $rules = "Y";
            
            /* alternatives */
            $altn = $this->alternative($arr_icao);


            $data = array(
                "ID"          => $flight,
                "RULES"       => $rules,
                "FLIGHTTYPE"  => "S",
                "NUMBER"      => "1",
                "ACTYPE"      => $aircraft,
                "WAKECAT"     => $wakecat,
                "EQUIPMENT"   => $company[$aircraft]["EQPT"],
                "TRANSPONDER" => "C",
                "DEPICAO"     => $dep_icao,
                "DEPTIME"     => $dep_time,
                "SPEEDTYPE"   => $speed_type,
                "SPEED"       => $speed,
                "LEVELTYPE"   => "F",
                "LEVEL"       => $level,
                "ROUTE"       => $route,
                "DESTICAO"    => $arr_icao,
                "EET"         => $eet,
                "ALTICAO"     => $altn['ALTN1'],
                "ALTICAO2"    => $altn['ALTN2'],
                "OTHER"       => $remark,
                "ENDURANCE"   => str_pad(floor(((substr($eet, 0, 2) * 60) + substr($eet, 2, 2) + 60 + 45)/60) . ((substr($eet, 0, 2) * 60) + substr($eet, 2, 2) + 60 + 45) % 60,  4, "0", STR_PAD_LEFT),
                "POB"         => rand(round(($company[$aircraft]["POB"]*15)/100), $company[$aircraft]["POB"]),
                "MTL"         => $company[$aircraft]["MTL"]
            );

            array_push($flights, $data);
            
            /* console log */
            echo implode(" ", $data) . " <b>OK</b><br>";
        }
        
        return $flights;
    }
    
    public function company()
    {
        $data = array(
            "GLO" => array(
                "OPR" => "GOL LINHAS AEREAS S.A",
                "B737" => array(
                    "REG" => array("PRGOF", "PRGOB", "PRVBV", "PRGOW", "PRGID"),
                    "POB" => 149,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                ),
                "B738" => array(
                    "REG" => array("PRGGN", "PRGTZ", "PRGTJ", "PRGTF", "PRGTT"),
                    "POB" => 183,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                )
            ),
            
            "TAM" => array(
                "OPR" => "TAM SA",
                "A319" => array(
                    "REG" => array("PRMAH", "PRMBW", "PTMZA", "PTTME", "PTTMD"),
                    "POB" => 156,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                ),
                "A320" => array(
                    "REG" => array("PTMZK", "PRMAD", "PTMZN", "PRMYF", "PRMHU"),
                    "POB" => 174,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                ),
                "A321" => array(
                    "REG" => array("PTMXD", "PTMXB", "PTMXG", "PTMXF", "PTMXE"),
                    "POB" => 186,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                ),
                "A330" => array(
                    "REG" => array("PTMVP", "PTMVU", "PTMVR", "PTMVH", "PTMVV"),
                    "POB" => 223,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                ),
                "B773" => array(
                    "REG" => array("PTMUC", "PTMUB", "PTMUA", "PTMUD", "PTMUB"),
                    "POB" => 365,
                    "EQPT" => "",
                    "MTL"  => "",
                    "PER"  => ""
                )
            ),

            "AZU" => array(
                "OPR" => "AZUL",
                "A320" => array(
                    "REG" => array("PRYRA", "PRYRB", "PRYRC", "PRYRD", "PRYRE", "PRYRF", "PRYRH", "PRYRI"),
                    "POB" => 195,
                    "EQPT" => "SDFGHIRWY",
                    "MTL"  => "A320AZU",
                    "PER"  => "C"
                ),
                "A332" => array(
                    "REG" => array("PRAIT", "PRAIU", "PRAIV", "PRAIW", "PRAIZ"),
                    "POB" => 247,
                    "EQPT" => "SDE2E3FGHIRWY",
                    "MTL"  => "A332AZU",
                    "PER"  => "D"
                ),
                "E190" => array(
                    "REG"  => array("PRAUA", "PRAUB", "PRAUC", "PRAUD", "PRAUE", "PRAUF", "PRAUH", "PRAUI", "PRAUJ", "PRAUK", "PRAUM", "PRAUN", "PRAUO", "PRAUP", "PRAUQ", "PRAXA", "PRAXB", "PRAXC", "PRAXD", "PRAXE", "PRAXF", "PRAXG", "PRAXH", "PRAXI", "PRAXJ", "PRAXK", "PRAXL", "PRAXN", "PRAXO", "PRAXP", "PRAXQ", "PRAXR", "PRAXS", "PRAXT", "PRAXU", "PRAXW", "PRAXX", "PRAXY", "PRAXZ", "PRAYA", "PRAYB", "PRAYC", "PRAYD", "PRAYE", "PRAYF", "PRAYG", "PRAYH", "PRAYI", "PRAYJ", "PRAYK", "PRAYL", "PRAYM", "PRAYN", "PRAYO", "PRAYQ", "PRAYR", "PRAYT", "PRAYU", "PRAYV", "PRAYW", "PRAYX", "PRAYY", "PRAYZ", "PRAZA", "PRAZB", "PRAZC", "PRAZD", "PRAZE", "PRAZF", "PRAZG", "PRAZH", "PRAZI", "PRAZL"),
                    "POB"  => 108,
                    "EQPT" => "SDFGHIRWY",
                    "MTL"  => "E190AZU",
                    "PER"  => "C"
                ),
                "AT72" => array(
                    "REG"  => array("PRAKA", "PRAKB", "PRAKC", "PRAKD", "PRAKF", "PRAQA", "PRAQB", "PRAQE", "PRAQH", "PRAQI", "PRAQJ", "PRAQK", "PRAQL", "PRAQM", "PRAQN", "PRAQO", "PRAQP", "PRAQQ", "PRAQR", "PRAQS", "PRAQT", "PRAQZ", "PRATB", "PRATE", "PRATG", "PRATH", "PRATJ", "PRATK", "PRATP", "PRATQ", "PRATR", "PRATU", "PRATV", "PRATW", "PRTKI", "PRTKJ", "PRTKK", "PRTKL", "PRTKM"),
                    "POB"  => 74,
                    "EQPT" => "SDFGHIRY",
                    "MTL"  => "AT76AZU",
                    "PER"  => "B"
                )
            ),

            "AVA" => array(
                "OPR" => "AVIANCA",
                "A319" => array(
                    "REG" => array("HCCKN", "HCCKO", "HCCKP", "HCCLF", "HCCSA", "N422AV", "N478TA", "N479TA", "N480TA", "N519AV", "N520TA", "N521TA", "N522TA", "N524TA", "N557AV", "N647AV", "N690AV", "N691AV", "N694AV", "N695AV", "N703AV", "N723AV", "N726AV", "N730AV", "N741AV", "N751AV", "N753AV"),
                    "POB" => 160,
                    "EQPT" => "SDE1E2E3FGHIJ4J5RWXY",
                    "MTL"  => "A319AVA",
                    "PER"  => "C"
                ),
                "A332" => array(
                    "REG" => array("N279AV", "N280AV", "N342AV", "N941AV", "N968AV", "N969AV", "N973AV", "N974AV", "N975AV"),
                    "POB" => 247,
                    "EQPT" => "SDE1E2E3FGHIJ4J5RWXY",
                    "MTL"  => "A332AVA",
                    "PER"  => "D"
                )
            ),

            "LAP" => array(
                "OPR" => "TAM",
                "A320" => array(
                    "REG" => array("PRMBH"),
                    "POB" => 195,
                    "EQPT" => "SDE2FGHIRWXYZ",
                    "MTL"  => "A320TAM",
                    "PER"  => "C"
                )
            ),

            "PAM" => array(
                "OPR" => "MAP",
                "AT43" => array(
                    "REG"  => array("PRMPO"),
                    "POB"  => 50,
                    "EQPT" => "SD",
                    "MTL"  => "AT43PAM",
                    "PER"  => "B"
                ),
                "AT72" => array(
                    "REG"  => array("PRMPY", "PRMPZ"),
                    "POB"  => 74,
                    "EQPT" => "SD",
                    "MTL"  => "AT72PAM",
                    "PER"  => "B"
                )
            ),

            "TTL" => array(
                "OPR" => "TOTAL",
                "B722" => array(
                    "REG" => array("PRTTO", "PRTTP", "PRTTW", "PTMTQ", "PTMTT"),
                    "POB" => 6,
                    "EQPT" => "SDGRWZ",
                    "MTL"  => "B722TTL",
                    "PER"  => "C"
                )
            )
        );

        return $data[$this->iata];
    }   
    
    public function write($flights)
    {
        
        foreach($flights as $item)
        {
            /* create a file */
            file_put_contents("/home/leonardomoreira.com.br/public/fpl/app.files/{$this->iata}/{$item["ACTYPE"]} - {$item["DEPICAO"]} {$item["DESTICAO"]} - {$item["ID"]}.fpl", "[FLIGHTPLAN]\nID={$item["ID"]}\nRULES={$item['RULES']}\nFLIGHTTYPE={$item['FLIGHTTYPE']}\nNUMBER={$item['NUMBER']}\nACTYPE={$item['ACTYPE']}\nWAKECAT={$item['WAKECAT']}\nEQUIPMENT={$item['EQUIPMENT']}\nTRANSPONDER={$item['TRANSPONDER']}\nDEPICAO={$item['DEPICAO']}\nDEPTIME={$item['DEPTIME']}\nSPEEDTYPE={$item['SPEEDTYPE']}\nSPEED={$item['SPEED']}\nLEVELTYPE={$item['LEVELTYPE']}\nLEVEL={$item['LEVEL']}\nROUTE={$item['ROUTE']}\nDESTICAO={$item['DESTICAO']}\nEET={$item['EET']}\nALTICAO={$item['ALTICAO']}\nALTICAO2={$item['ALTICAO2']}\nOTHER={$item['OTHER']}\nENDURANCE={$item['ENDURANCE']}\nPOB={$item['POB']}\nMTL={$item['MTL']}");
        }    
    }
    
    public function alternative($icao)
    {
        $data = array(
            "SAAR" => array(
                "ALTN1" => "SACO",
                "ALTN2" => "SAEZ"
            ),
            "SAEZ" => array(
                "ALTN1" => "SBPA",
                "ALTN2" => "SAAR"
            ),
            "SAZS" => array(
                "ALTN1" => "SAZB",
                "ALTN2" => "SAZN"
            ),
            "SBAQ" => array(
                "ALTN1" => "SBGL",
                "ALTN2" => "SBGR"
            ),
            "SBAR" => array(
                "ALTN1" => "SBRF",
                "ALTN2" => "SBSV"
            ),
            "SBBE" => array(
                "ALTN1" => "SBSL",
                "ALTN2" => "SBMQ"
            ),
            "SBBH" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBGL"
            ),
            "SBBR" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBGO"
            ),
            "SBBT" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBKP"
            ),
            "SBBV" => array(
                "ALTN1" => "SBSN",
                "ALTN2" => "SBEG"
            ),
            "SBCB" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCF"
            ),
            "SBCF" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBGL"
            ),
            "SBCG" => array(
                "ALTN1" => "SBGO",
                "ALTN2" => "SBCY"
            ),
            "SBCH" => array(
                "ALTN1" => "SBCT",
                "ALTN2" => "SBPA"
            ),
            "SBCN" => array(
                "ALTN1" => "SBBR",
                "ALTN2" => "SBGO"
            ),
            "SBCT" => array(
                "ALTN1" => "SBKP",
                "ALTN2" => "SBFL"
            ),
            "SBCX" => array(
                "ALTN1" => "SBCT",
                "ALTN2" => "SBFL"
            ),
            "SBCY" => array(
                "ALTN1" => "SBGO",
                "ALTN2" => "SBCG"
            ),
            "SBCZ" => array(
                "ALTN1" => "SBPB",
                "ALTN2" => "SBRB"
            ),
            "SBDN" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBKP"
            ),
            "SBEG" => array(
                "ALTN1" => "SBBV",
                "ALTN2" => "SBSN"
            ),
            "SBFI" => array(
                "ALTN1" => "SBPA",
                "ALTN2" => "SBCT"
            ),
            "SBFL" => array(
                "ALTN1" => "SBPA",
                "ALTN2" => "SBCT"
            ),
            "SBFN" => array(
                "ALTN1" => "SBRF",
                "ALTN2" => "SBNT"
            ),
            "SBFZ" => array(
                "ALTN1" => "SBRF",
                "ALTN2" => "SBNT"
            ),
            "SBGL" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCF"
            ),
            "SBGO" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBBR"
            ),
            "SBGR" => array(
                "ALTN1" => "SBGL",
                "ALTN2" => "SBCT"
            ),
            "SBIL" => array(
                "ALTN1" => "SBAR",
                "ALTN2" => "SBSV"
            ),
            "SBIZ" => array(
                "ALTN1" => "SBSL",
                "ALTN2" => "SBBE"
            ),
            "SBJP" => array(
                "ALTN1" => "SBMO",
                "ALTN2" => "SBRF"
            ),
            "SBJU" => array(
                "ALTN1" => "SBRF",
                "ALTN2" => "SBFZ"
            ),
            "SBJV" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCT"
            ),
            "SBKG" => array(
                "ALTN1" => "SBFZ",
                "ALTN2" => "SBMO"
            ),
            "SBKP" => array(
                "ALTN1" => "SBGL",
                "ALTN2" => "SBCT"
            ),
            "SBLO" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCT"
            ),
            "SBMA" => array(
                "ALTN1" => "SBSL",
                "ALTN2" => "SBBE"
            ),
            "SBMG" => array(
                "ALTN1" => "SBCT",
                "ALTN2" => "SBLO"
            ),
            "SBMO" => array(
                "ALTN1" => "SBNT",
                "ALTN2" => "SBRF"
            ),
            "SBMQ" => array(
                "ALTN1" => "SBSL",
                "ALTN2" => "SBBE"
            ),
            "SBNF" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCT"
            ),
            "SBNT" => array(
                "ALTN1" => "SBFZ",
                "ALTN2" => "SBRF"
            ),
            "SBPA" => array(
                "ALTN1" => "SBCT",
                "ALTN2" => "SBFL"
            ),
            "SBPJ" => array(
                "ALTN1" => "SBGO",
                "ALTN2" => "SBBR"
            ),
            "SBPL" => array(
                "ALTN1" => "SBSV",
                "ALTN2" => "SBAR"
            ),
            "SBPS" => array(
                "ALTN1" => "SBVT",
                "ALTN2" => "SBSV"
            ),
            "SBPV" => array(
                "ALTN1" => "SBEG",
                "ALTN2" => "SBRB"
            ),
            "SBRB" => array(
                "ALTN1" => "SBEG",
                "ALTN2" => "SBPV"
            ),
            "SBRF" => array(
                "ALTN1" => "SBNT",
                "ALTN2" => "SBMO"
            ),
            "SBRJ" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCF"
            ),
            "SBRP" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBGR"
            ),
            "SBSJ" => array(
                "ALTN1" => "SBGL",
                "ALTN2" => "SBGR"
            ),
            "SBSL" => array(
                "ALTN1" => "SBBE",
                "ALTN2" => "SBTE"
            ),
            "SBSN" => array(
                "ALTN1" => "SBEG",
                "ALTN2" => ""
            ),
            "SBSP" => array(
                "ALTN1" => "SBGL",
                "ALTN2" => "SBCT"
            ),
            "SBSR" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBKP"
            ),
            "SBSV" => array(
                "ALTN1" => "SBMO",
                "ALTN2" => "SBAR"
            ),
            "SBTC" => array(
                "ALTN1" => "SBAR",
                "ALTN2" => "SBSV"
            ),
            "SBTE" => array(
                "ALTN1" => "SBFZ",
                "ALTN2" => "SBSL"
            ),
            "SBUL" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBBR"
            ),
            "SBUR" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBBR"
            ),
            "SBVT" => array(
                "ALTN1" => "SBCF",
                "ALTN2" => "SBGL"
            ),
            "SCEL" => array(
                "ALTN1" => "SAEZ",
                "ALTN2" => "SCIE"
            ),
            "SGAS" => array(
                "ALTN1" => "SBCG",
                "ALTN2" => "SBFI"
            ),
            "SLVR" => array(
                "ALTN1" => "SBGR",
                "ALTN2" => "SBCG"
            ),
            "SNVB" => array(
                "ALTN1" => "SBMO",
                "ALTN2" => "SBAR"
            ),
            "SPIM" => array(
                "ALTN1" => "SPHI",
                "ALTN2" => "SPSO"
            ),
            "SULS" => array(
                "ALTN1" => "SBPA",
                "ALTN2" => "SAEZ"
            ),
            "SUMU" => array(
                "ALTN1" => "SBPA",
                "ALTN2" => "SAEZ"
            )
        );

       return $data[$icao];
    }
    
}