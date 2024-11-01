<?php
/*
Plugin Name: TTLive
Plugin URI: http://www.svbb-tischtennis.de/
Description: A simple wordpress plugin to get the data from the ttlive-system and show it on my wp-post or wp-page
Version: 0.9.7
Author: finalan
Author URI: http://www.svbb-tischtennis.de
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

function TTLive_func( $atts, $content = null ) {
	$a = shortcode_atts( array(
		'elementname' => '', 		// Rueckgabe-Element "Spielplan", "Tabelle", "14Tage", "Rangliste" oder "Hallenplan"
		'mannschaft_id' => get_post_meta(get_the_ID(), "mannschaft_id", true), 		// TTLive Mannschaft ID
		'staffel_id' => get_post_meta(get_the_ID(), "staffel_id", true), 		// TTLive Staffel ID
		'tableclassname' => '', 	// Klassenname der Tabelle
		'own_team' => '', 			// Name des eigenen Teams
		'runde' => 1,				// Vorrunde = 1 (default), Rückrunde = 2
		'showxdays' => 0,			// 14Tage: Anzahl der Tage die dargestellt werden sollen
		'max' => 0,					// 14Tage: Anzahl der Tage die maximal dargestell werden sollen
		'widget' => 0,				// 14Tage: Für die Darstellung in einem Widget
		//START nur für die Tabelle
		'teamalias' => '',			// Teamname:Alias;Teamname2:Alias2;
		'showleague' => true,		// Ueberschrift-Anzeige der Liga
		'showmatchecount' => true,	// Anzahl der gemachten Spiele
		'showsets' => true,			// Anzahl der gewonnenen/verlorenen Saetze
		'showgames' => true,		// Anzahl der gewonnenen/verlorenen Spiele
		'aufstiegsplatz' => 2,		// Aufstiegsplaetze bis
		'abstiegsplatz' => 9,		// Abstiegsplaetze ab
		'relegation' => '',			// Relegationsplätze
		//ENDE nur für Tabelle
        'saison' => '',             //Hallenplan: Das Jahr der Saison
        'display_all' => false,      //Rangliste: zeigt alle oder nur Spieler mit gültiger LivePZ
		'display_type' => false,	// die letzten 14Tage (0 - default) oder die naechsten 14Tage (1)		
		'refresh' => get_option('TTLive_refreshHours'),				// Anzahl Stunden bis die Daten erneut vom Live-System aktualisiert werden sollen		
	), $atts );
	//print_r($atts);
	
	if ($a['runde'] == 1) { $runde = "1"; }
	if ($a['runde'] == 2) { $runde = "2"; }
	
	$dir = plugin_dir_path( __FILE__ );
	
	$retval = "";
	$a['baseurl'] = get_option('TTLive_baseurl');
	$a['filename'] = $dir."/ttlive-files/ttliveDS" . $a['staffel_id'] . "-" . $a['mannschaft_id'] . "_".$runde.".xml";
	$a['url'] = $a['baseurl']."/Export/default.aspx?TeamID=".$a['mannschaft_id']."&WettID=".$a['staffel_id']."&Format=XML&Runde=".$runde."&SportArt=96&Area=TeamReport";
	
	switch ($a['elementname']) {
		case "Mannschaft":			
			$retval = getTTLiveMannschaft($a);
			break;
		case "Tabelle":
			$retval = getTTLiveTabelle($a);
			break;
		case "Spielplan":
			$retval = getTTLiveTeamSpielplan($a);
			break;
		case "14Tage":
			$area = "";
			if ( $a['display_type'] == 0 ):
				$area .= "&Area=PlanLast";
			else:
				$area .= "&Area=PlanNext";
			endif;
			$a['filename'] = $dir."/ttlive-files/ttliveDS" . "14Days" .$area . ".xml";
			$a['url'] = $a['baseurl']."/Export/default.aspx?SpartenID=".get_option('TTLive_divisionID')."&Format=XML&SportArt=96&".$area;
			if ( $a['widget'] == 1 ) {
				$retval = getTTLive14TageDataForWidget($a);
			} else {
				$retval = getTTLive14Tage($a);
			}
			break;
        case "Rangliste":
            $a['filename'] = $dir."/ttlive-files/ttliveRangliste.xml";
            $a['url'] = $a['baseurl']."/Export/default.aspx?SpartenID=".get_option('TTLive_divisionID')."&Format=XML&SportArt=96&Area=VereinLivePZ";
            $retval = getTTLiveRangliste($a);
            break;
        case "Hallenplan":
            if ($a['saison'] != ''){
                $a['filename'] = $dir."/ttlive-files/ttliveHallenplan_".$runde.".xml";
                $a['url'] = $a['baseurl']."/Export/default.aspx?SpartenID=".get_option('TTLive_divisionID')."&Format=XML&SportArt=96&Saison=".$a['saison']."&Runde=".$runde."&Area=Hallenplan";
                $retval = getTTLiveHallenplan($a);
            } else {
                $retval = "Das Attribut Saison für den Hallenplan wurde nicht angegeben";
            }
            break;
		default:
			$retval = "Konnte Elementname nicht auswerten";
	}
	return $retval;
}

function refreshTTLiveData(&$params) {
/***
        *   Cache XML-File to reduce traffic
        *   IF XML is older than x hour -> renew (store XML in TMP folder)
        */
		
		$secondsToRefresh = $params['refresh'] * 3600;
				
		if(!file_exists($params['filename']) || time()-$secondsToRefresh > filemtime($params['filename'])) 
		{
			$url = $params['url'];			

			$html = wp_remote_retrieve_body( wp_remote_get($params['url']) );
											
			if ($html != '') {
				$myhandle = fopen($params['filename'], "w");
				if (!fwrite($myhandle, $html)) {
					print "<br />Kann in die Datei ". $params['filename'] ." nicht schreiben";
					exit;
				}
			}			
		}
}

function getTTLiveTeamSpielplan(&$params){
		$debug = 0;
		/**
		 * XML aus lokalem tmp Folder laden
		 */
		$tableclassname = get_option('TTLive_tableclassname_TeamSpielplan');
		if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }
		
		refreshTTLiveData($params);
  		if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL)) 
		{
          		  		  
		if($params['showleague']) 
		{	
			$plan = '<h4 style="text-align:center"><a href='.$xml->Ligalink.'>' . $xml->Liga. '</a></h4>';
		}
		  
          $plan .= "<table class='" . $tableclassname . "'>\n";
          $plan .= "<thead><tr><th style='text-align:center;'>Datum</th>\n";
          $plan .= "<th style='text-align:center;'>Zeit</th>\n";
          $plan .= "<th style='text-align:right;'>Heimteam</th>\n";
          $plan .= "<th style='text-align:center;'>&nbsp;</th>\n";
          $plan .= "<th style='text-align:left;'>Gastteam</th>\n";               
          $plan .= "<th style='text-align:center;'>Ergebnis</th>\n"; 
          $plan .= "</tr></thead>\n"; 
          $tableRow = "even";
		  $zeile = 0;

          $dateOld = null;
		  foreach($xml->Content->Spielplan->Spiel as $key => $attribute) 
          {
			  if (strftime('%d.%m.%Y', strtotime($attribute->Datum)) != $dateOld)
              {
                  if ($tableRow == "even")
                  {
                      $tableRow = "odd";
                  }
                  else
                  {
                       $tableRow = "even";
                  }
              }
			  $zeile++;
			  
			  $dateOld = strftime('%d.%m.%Y', strtotime($attribute->Datum));
			  $thedate = strftime('%d.%m.%Y', strtotime($attribute->Datum));
              $plan .= "<tr";
				if ($zeile % 2 !=0) {
					//ungerade
					$plan .=  " class='even'>";
				} else {
					//gerade
					$plan .=  " class='odd'>";
				}
              $plan .="<td style='text-align: center;'>".$thedate ."</td>\n";
              $plan .= "<td>".$attribute->Zeit ."</td>\n";
              if (strstr($attribute->Heimmannschaft, $params['own_team']))
              {
                  $isHeimteam = true;
                  $plan .= "<td style='text-align:right;'><b>".$attribute->Heimmannschaft ."</b></td>\n";
              }
              else
              {
                  $isHeimteam = false;
                  $plan .= "<td style='text-align:right;'>".$attribute->Heimmannschaft ."</td>\n";
              }
              $plan .= "<td>-</td>";
              if (strstr($attribute->Gastmannschaft, $params['own_team']))
              {
                  $isGastteam = true;
                  $plan .= "<td style='text-align:left;'><b>".$attribute->Gastmannschaft ."</b></td>\n";
              }
              else
              {
                  $isGastteam = false;
                  $plan .= "<td style='text-align:left;'>".$attribute->Gastmannschaft ."</td>\n";
              }              
              $color = "#333333";
			  if (strstr($attribute->Ergebnis, ":")){
				$array = explode ( ':', $attribute->Ergebnis );
              
				  if ((!$isHeimteam) || (!$isGastteam))
				  {
					if ((intval($array[0])) > (intval($array[1])))
					{
						if ($isHeimteam)
						{
							$color="green";
						}
						else
						{
							$color="red";
						}
					}
					if ((intval($array[1])) > (intval($array[0])))
					{
						if ($isGastteam)
						{
							$color="green";
						}
						else
						{
							$color="red";
						}
					}
				  }
			  }
              $plan .= "<td style='color:".$color ."; text-align: center;'>";
              if ($attribute->Link) 
              { 
                  $plan .="<a style=\"color:".$color ."\" href=\"".htmlentities($attribute->Link) ."\" target=\"_blank\">"; 
              } 
                  $plan .=$attribute->Ergebnis; 
              if ($attribute->Link)
              {
                  $plan .="</a>"; 
              } 
              $plan .= "</td>\n";
              $plan .= "</tr>\n";
          }
          $plan .= "</table>";          
          return $plan;
      } 
      else 
      {
          return 'Konnte TT-Live-XML nicht laden';
      }	            
}

function getTTLiveTabelle(&$params){
	$debug = 0;
	/**
	 * XML aus lokalem tmp Folder laden
	 */
	$tableclassname = get_option('TTLive_tableclassname_Tabelle');
	if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }
	
	refreshTTLiveData($params);
	
	if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL)) 
	{		
		$params['own_team'] = utf8_encode($params['own_team']);
		
		$teamurl = $params['baseurl'].'/?L1=Ergebnisse&L2=TTStaffeln&L2P='.$params['staffel_id'].'&L3=Mannschaften&L3P='.$params['mannschaft_id'];
		
		// Ersetzungen aus Parameter in Array umwandeln
		if($params['teamalias']) 
		{
			$teams = explode(';' , $params['teamalias']);
			foreach($teams as $team) 
			{
				$tmpteam = explode(':' , $team);
				$aliases[$tmpteam[0]] = $tmpteam[1];
			}
		}
		// Split Relegationsplätze
		$relegations = array();
		if($params['relegation'])
		{
			$relegations = explode(",", $params['relegation']);						
		}
		//START Tablehead
		if($params['showleague']) 
		{
			$ladder = '<h4 style="text-align:center"><a href='.$xml->Ligalink.'>' . $xml->Liga. '</a></h4>';
		}
		$ladder .= "<table class='" . $tableclassname . "'>\n";
		$ladder .= '<thead><tr><th>Platz</th><th style="text-align:left;">Team</th>';
		if($params['showmatchecount']) 
		{
			$ladder .= '<th style="text-align:center">Anz</th>';
		}
		if($params['showsets']) 
		{
			$ladder .= '<th style="text-align:center;">SatzDif</th>';
		}
		if($params['showgames']) 
		{
			$ladder .= '<th style="text-align:center">Spiele</th>';
		}
		$ladder .=  '<th style="text-align:center">Punkte</th></tr></thead>'."\n";
		//ENDE Tablehead
		$zeile=0;
		foreach($xml->Content->Tabelle->Mannschaft as $key => $attribute) 
		{
			$zeile++;
			$teamname = (string) $attribute->Mannschaft;		        
			if($params['teamalias']) 
			{
				if(array_key_exists((string) $attribute->Mannschaft , $aliases))
				{
					$teamname = $aliases[(string) $attribute->Mannschaft];
				}
			} 
			else 
			{
				$teamname = (string) $attribute->Mannschaft;
			}
			
						
			//Tablerow --> even/odd and aufstieg/abstiegsplatz
			$ladder .= '<tr class="';
			
			if ($zeile <= $params['aufstiegsplatz'])
			{
				$ladder .= 'cAufstieg';
			}
			if ($zeile >= $params['abstiegsplatz'])
			{
				$ladder .= 'cAbstieg';
			}
			if (in_array(strval($zeile), $relegations))
			{
				$ladder .= 'cRelegation';
			}
						
			if ($zeile % 2 !=0) {
				//ungerade
				$ladder .=  ' even';
			} else {
				//gerade
				$ladder .=  ' odd';
			}
			
			if (strstr($attribute->Mannschaft, $params['own_team'])) 
			{
				$ladder .= ' cOwnTeam" onclick="window.open(\''.$teamurl.'\', \'_blank\', \'\'); return false;">';
			} else {
				$ladder .= '">';				
			}
			
			//Tabledata...			
			$ladder .= "<td style='width:17px;text-align:center;'>".$attribute->Platz ."</td><td style='width:150px;text-align:left;'>". $teamname ."</td>\n";
			
			if($params['showmatchecount'] ) 
			{				
				$ladder .= '<td style="width:30px;text-align:center;">'.  $attribute->Spiele ."</td>\n";				
			}		    
			if($params['showsets']) 
			{				
				$ladder .= '<td style="width:50px;text-align:center;">' .  $attribute->SaetzeDif . "</td>\n";				
			}
			if($params['showgames'] ) 
			{				  
				$ladder .= '<td style="width:50px;text-align:center;">' .  $attribute->SpielePlus . ":" .$attribute->SpieleMinus . "</td>\n";                    }
			
				$ladder .=  "<td style='width:45px;text-align:center;'>".$attribute->PunktePlus . ":" .$attribute->PunkteMinus . "</td></tr>\n";
			}       
			$ladder .=  "</table>\n";
			return $ladder;
		} 
		else 
		{
			return 'Konnte TT-Live-XML nicht laden';
		}
}

function xsort(&$nodes, $child_name, $order=SORT_ASC)
{
    $sort_proxy = array();

    foreach ($nodes as $k => $node) {
        $sort_proxy[$k] = (string) $node->$child_name;
    }

    array_multisort($sort_proxy, $order, $nodes);
}

function getTTLiveHallenplan(&$params){
    $debug = 0;
    /**
     * XML aus lokalem tmp Folder laden
     */
    $tableclassname = get_option('TTLive_tableclassname_Hallenplan');
    if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }

    refreshTTLiveData($params);
    if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL))
    {

        $nodes = $xml->xpath('/Heimspielplan/Content/Spiel');

        $plan = "<table class='" . $tableclassname . "'>\n";
        $plan .= "<tr><th>Tag</th><th>Datum</th>\n";
        $plan .= "<th>Zeit</th>\n";
        $plan .= "<th>Heim</th>\n";
        $plan .= "<th>Gast</th>\n";
        $plan .= "<th>Staffel</th>\n";
        $plan .= "<th>Bem.</th>\n";
        $plan .= "<th>Ergebnis</th>\n";
        $plan .= "</tr>\n";
        $zeile = 0;

        foreach($nodes as $key => $attribute)
        {
            $zeile++;

            $plan .= "<tr";
            if ($zeile % 2 !=0) {
                //ungerade
                $plan .=  " class='even'>";
            } else {
                //gerade
                $plan .=  " class='odd'>";
            }
            $plan .= "<td>$attribute->Tag</td>\n";
            $plan .= "<td>".strftime('%d.%m.%Y', strtotime($attribute->Datum))."</td>\n";
            $plan .= "<td>$attribute->Zeit</td>\n";
            $plan .= "<td>$attribute->Heimmannschaft</td>\n";
            $plan .= "<td>$attribute->Gastmannschaft</td>\n";
            $plan .= "<td>$attribute->Staffelname</td>\n";
            $plan .= "<td>$attribute->Kennzeichnung</td>\n";
            $plan .= '<td><a style="color:'.returnColorByResult($attribute->Ergebnis, true).'" href="'.$attribute->Link.'">'.$attribute->Ergebnis.'</a></td>'."\n";
            $plan .= "</tr>\n";

        }
        $plan .= "</table>\n";
        $plan .= "<br/>\n";
        return $plan;
    }
    else
    {
        return 'Konnte TT-Live-XML nicht laden';
    }
}

function getTTLiveRangliste(&$params){
    $debug = 0;
    /**
     * XML aus lokalem tmp Folder laden
     */
    $tableclassname = get_option('TTLive_tableclassname_Rangliste');
    if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }

    refreshTTLiveData($params);
    if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL))
    {

        $nodes = $xml->xpath('/LivePZ/Content/Spieler');

        $plan = "<table class='" . $tableclassname . "'>\n";
        $plan .= "<tr><th>Nr.</th><th>Spieler</th>\n";
        $plan .= "<th>Geb.jahr</th>\n";
        $plan .= "<th>Geschlecht</th>\n";
        $plan .= "<th>LivePZ</th>\n";
        $plan .= "<th>Stichtag 1</th>\n";
        $plan .= "<th>Stichtag 2</th>\n";
        $plan .= "<th>Stichtag 3</th>\n";
        $plan .= "</tr>\n";
        $zeile = 0;

        foreach($nodes as $key => $attribute)
        {
            $showSpieler = true;
            if (!$params['display_all']) {
                if ($attribute->LivePZ == "k.A."){
                    $showSpieler = false;
                }
            }
            if ($showSpieler){
                $zeile++;


                $plan .= "<tr";
                if ($zeile % 2 !=0) {
                    //ungerade
                    $plan .=  " class='even'>";
                } else {
                    //gerade
                    $plan .=  " class='odd'>";
                }
                $plan .= "<td>$zeile</td>\n";
                $plan .= "<td>$attribute->Spielername</td>\n";
                $plan .= "<td>$attribute->Gebdatum</td>\n";
                $plan .= "<td>$attribute->Geschlecht</td>\n";
                $plan .= "<td>$attribute->LivePZ</td>\n";
                $plan .= "<td>$attribute->Stichtag1</td>\n";
                $plan .= "<td>$attribute->Stichtag2</td>\n";
                $plan .= "<td>$attribute->Stichtag3</td>\n";
                $plan .= "</tr>\n";
            }
        }
        $plan .= "</table>\n";
        $plan .= "<br/>\n";
        return $plan;
    }
    else
    {
        return 'Konnte TT-Live-XML nicht laden';
    }
}

function returnColorByResult($result, $isHeimteam){
    $array = explode ( ':', $result );
    $color="black";
    if ((intval($array[0])) > (intval($array[1])))
    {
        if ($isHeimteam)
        {
            $color="green";
        }
        else
        {
            $color="red";
        }
    }
    if ((intval($array[1])) > (intval($array[0])))
    {
        if (!$isHeimteam)
        {
            $color="green";
        }
        else
        {
            $color="red";
        }
    }
    return $color;
}

function getTTLive14Tage(&$params){
	$debug = 0;
	/**
	 * XML aus lokalem tmp Folder laden
	 */
	$tableclassname = get_option('TTLive_tableclassname_TeamSpielplan');
	if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }
	
	refreshTTLiveData($params);
	if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL)) 
	{
		//works only with php 5.3 and higher
		//$expiration_date = new DateTime();
		//$date_interval = new DateInterval('P'.$params['showxdays'].'D');
		//$date_interval->invert = $params['display_type'];
		//$expiration_date->sub($date_interval);
		
		if ( $params['display_type'] == "0" ) {
			$op = "-";
		} else {
			$op = "+";			
		}
		
		$expiration_date = new DateTime(date("d-m-Y",strtotime($op.$params['showxdays'].' day')));
				
		if ($params['display_type'] == 0) {
			$nodes = $xml->xpath('/LetzteSpiele/Content/Spiel');
	
			// Sort by date, descending
			xsort($nodes, 'Datum', SORT_DESC);
		} else {
			$nodes = $xml->xpath('/NachsteSpiele/Content/Spiel');
		}
		$plan = "<table class='" . $tableclassname . "'>\n";
		$plan .= "<tr><th></th><th>Datum</th>\n";
		$plan .= "<th>Zeit</th>\n";
		$plan .= "<th style='text-align:left'>Staffel</th>\n";
		$plan .= "<th style='text-align:right'>Heimteam</th>\n";
		$plan .= "<th style='text-align:center'>&nbsp;</th>\n";
		$plan .= "<th style='text-align:left'>Gastteam</th>\n";               
		if ($params['display_type']==0) 
		{
			$plan .= "<th>Erg.</th>\n"; 
		}
		$plan .= "</tr>\n"; 
		$zeile = 0;
		  
		foreach($nodes as $key => $attribute) 
		{
			$zeile++;			
			$dateOld = $attribute->Datum;
			$time = $attribute->Zeit;
			if ($time == "00:00") { $time = ""; }
			$thedate = new Datetime(strftime('%d.%m.%Y', strtotime($attribute->Datum)));			
			$hide = false;
			if ($params['showxdays'] > 0 ) {
				if ( $params['display_type'] ) {
					if ( $thedate > $expiration_date ) { $hide = true; }	
				} else {
					if ( $thedate < $expiration_date ) { $hide = true; }
				}
			}
			if ($params['max'] > 0 ) {
				if ( $zeile > $params['max'] ) { $hide = true; }
			}
			
			if ( !$hide) {
				$plan .= "<tr";
				if ($zeile % 2 !=0) {
					//ungerade
					$plan .=  " class='even'>";
				} else {
					//gerade
					$plan .=  " class='odd'>";
				}						 
				$plan .= '<td>'.$attribute->Tag.'</td><td>'.$thedate->format('d.m.').'</td>'."\n";
				$plan .= "<td>$time</td>\n";
				
				$staffel = $attribute->Staffelname;
				$search = array("Klasse", "Schüler", "Senioren 40", "Herren, ", "Bezirksklasse", "Bezirksliga", "Kreisliga", "Kreisklasse", "Landesliga", "Nord", "Süd", "Ost", "West", "Mitte");
				$replace = array("Kl", "Schü.", "S40", "", "BK", "BL", "KL", "KK", "LL", "N", "S", "O", "W", "M");
				$newstaffel = str_replace($search, $replace, $staffel);
				if ($staffel[0] == $attribute->Staffelname):
					$newstaffel = $attribute->Staffelname;
				endif;
				
				$plan .= "<td>".$newstaffel."</td>\n";
				$isHeimteam = false;
				if ((strstr($attribute->Heimmannschaft, "Herren"))
                    or  (strstr($attribute->Heimmannschaft, "Jungen"))
                    or  (strstr($attribute->Heimmannschaft, "Schüler"))
                    or (strstr($attribute->Heimmannschaft, "Damen"))
                    or  (strstr($attribute->Heimmannschaft, "Senioren"))
                    or  (strstr($attribute->Heimmannschaft, "Mini"))
                )
				{
					$isHeimteam = true;
					$plan .= "<td style='text-align:right;'><b>".$attribute->Heimmannschaft."</b></td>\n";
				}
				else
				{
				  $plan .= "<td style='text-align:right;'>".$attribute->Heimmannschaft."</td>\n";
				}
				$plan .= "<td> -</td>";
				if ((strstr($attribute->Gastmannschaft, "Herren"))
                    or  (strstr($attribute->Gastmannschaft, "Jungen"))
                    or (strstr($attribute->Gastmannschaft, "Schüler"))
                    or (strstr($attribute->Gastmannschaft, "Damen"))
                    or  (strstr($attribute->Gastmannschaft, "Senioren"))
                    or  (strstr($attribute->Gastmannschaft, "Mini"))
                    )
				{
					$plan .= "<td><b>".$attribute->Gastmannschaft."</b></td>\n";
				}
				else
				{
				  $plan .= "<td>".$attribute->Gastmannschaft."</td>\n";
				}                    
				if ($params['display_type']==0)  /*Rueckschau*/
				{
                    $color = returnColorByResult($attribute->Ergebnis, $isHeimteam);

					if ($color!="black")
					{
						$plan .= "<td style='text-align:center; color:".$color ."'>";
					}
					else
					{
						$plan .= "<td style='text-align:center;'>";
					}
			
					if ($attribute->Link) 
					{ 
						$plan .="<a style=\"text-align: center; color:".$color ."\" href=\"".htmlentities($attribute->Link) ."\" target=\"_blank\">"; 
					} 
					$plan .=$attribute->Ergebnis; 
					if ($attribute->Link)
					{
						$plan .="</a>"; 
					} 
					$plan .= "</td>\n";
				} 
				$plan .= "</tr>\n";
			}
		}
		$plan .= "</table>\n";
		$plan .= "<br/>\n";
		return $plan;
	} 
	else 
	{
		return 'Konnte TT-Live-XML nicht laden';
	}
}

/**
 * @param $params
 * @return bool|string
 */
function getTTLive14TageDataForWidget(&$params){
	$debug = 0;
	/**
	 * XML aus lokalem tmp Folder laden
	 */
	$tableclassname = get_option('TTLive_tableclassname_TeamSpielplan');
	if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }
	
	refreshTTLiveData($params);
	if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL)) 
	{
		//works only with php 5.3 and higher
		//$expiration_date = new DateTime();
		//$date_interval = new DateInterval('P'.$params['showxdays'].'D');
		//$date_interval->invert = $params['display_type'];		
		//$expiration_date->sub($date_interval);
		
		if ( $params['display_type'] == "0" ) {
			$op = "-";
		} else {
			$op = "+";			
		}
		
		$expiration_date = new DateTime(date("d-m-Y",strtotime($op.$params['showxdays'].' day')));
		
		if ($params['display_type'] == 0) {
			$nodes = $xml->xpath('/LetzteSpiele/Content/Spiel');
	
			// Sort by date, descending
			xsort($nodes, 'Datum', SORT_DESC);
		} else {
			$nodes = $xml->xpath('/NachsteSpiele/Content/Spiel');
		}
		
		$zeile = 0;
		$plan = '<dl>';
		foreach($nodes as $key => $attribute) 
		{
			$zeile++;			
			$dateOld = $attribute->Datum;
			$time = $attribute->Zeit;
			if ($time == "00:00") { $time = ""; }
			$thedate = new Datetime(strftime('%d.%m.%Y', strtotime($attribute->Datum)));			
			$hide = false;
			if ($params['showxdays'] > 0 ) {
				if ( $params['display_type'] ) {
					if ( $thedate > $expiration_date ) { $hide = true; }	
				} else {
					if ( $thedate < $expiration_date ) { $hide = true; }
				}
			}
			if ($params['max'] > 0 ) {
				if ( $zeile > $params['max'] ) { $hide = true; }
			}
			
			if ( !$hide) {		
							 
				$plan .= '<dt>'.$attribute->Tag.', '.$thedate->format('d.m.Y');
				$plan .= " - $time Uhr<br />";
				$staffel = explode(", ", $attribute->Staffelname);
				$search = array("Bezirksklasse", "Beziksliga", "Kreisliga", "Kreisklasse", "Landesliga", "Nord", "Süd", "Ost", "West", "Mitte", "Pokal Herren", "Pokal Damen");
				$replace = array("BK", "BL", "KL", "KK", "LL", "N", "S", "O", "W", "M", "Pokal He", "Pokal Da");
				$newstaffel = str_replace($search, $replace, $staffel[1]);
				if ( $newstaffel == $attribute->Staffelname ) {
					$newstaffel = $attribute->Staffelname;
				}
				$plan .= "<em>$attribute->Staffelname</em></dt>\n";
				$isHeimteam = false;
				if ((strstr($attribute->Heimmannschaft, "Herren"))
                    or  (strstr($attribute->Heimmannschaft, "Jungen"))
                    or  (strstr($attribute->Heimmannschaft, "Schüler"))
                    or (strstr($attribute->Heimmannschaft, "Damen"))
                    or  (strstr($attribute->Heimmannschaft, "Senioren"))
                    or  (strstr($attribute->Heimmannschaft, "Mini"))
                )
				{
					$isHeimteam = true;
					$plan .= "<dd><em>".$attribute->Heimmannschaft."</em>\n";
				}
				else
				{
				  $plan .= "<dd>".$attribute->Heimmannschaft."\n";
				}
				$plan .= " - ";
				if ((strstr($attribute->Gastmannschaft, "Herren"))
                    or (strstr($attribute->Gastmannschaft, "Jungen"))
                    or  (strstr($attribute->Gastmannschaft, "Schüler"))
                    or (strstr($attribute->Gastmannschaft, "Damen"))
                    or  (strstr($attribute->Gastmannschaft, "Senioren"))
                    or  (strstr($attribute->Gastmannschaft, "Mini"))
                )
				{
					$plan .= "<em>$attribute->Gastmannschaft</em><br />\n";
				}
				else
				{
				  $plan .= $attribute->Gastmannschaft."<br />\n";
				}                    
				if ($params['display_type']==0)  /*Rueckschau*/
				{
                    $color = returnColorByResult($attribute->Ergebnis, $isHeimteam);

                    $resultPrefix = 'Ergebnis: ';
                    if ($isHeimteam){
                        if ($color == "green"){
                            $resultPrefix = 'Heimsieg: ';
                        }
                    } else {
                        if ($color == "green"){
                            $resultPrefix = 'Auswärtssieg: ';
                        }
                    }

                    $plan .= $resultPrefix;
								
					if ($attribute->Link) 
					{ 
						$plan .="<a href=\"".htmlentities($attribute->Link) ."\" target=\"_blank\" style=\"color:".$color ."\">";
					} 
					$plan .= $attribute->Ergebnis; 
					if ($attribute->Link)
					{
						$plan .="</a>"; 
					} 
					//$plan .= "</dd>\n";
				} 
				$plan .= "</dd>\n";
			}
		}
		$plan .= "</dl>\n";
		if ( $zeile ) { return $plan; } else { return false; }
	} 
	else 
	{
		return 'Konnte TT-Live-XML nicht laden';
	}
}

function getTTLiveMannschaft(&$params) {
	$debug = 0;
	/**
	 * XML aus lokalem tmp Folder laden
	 */
	
	$tableclassname = get_option('TTLive_tableclassname_TeamSpielplan');
	
	if ($params['tableclassname'] != '') { $tableclassname = $params['tableclassname']; }
	
	refreshTTLiveData($params);

	if($xml = simplexml_load_file($params['filename'], NULL, ($debug==1)?LIBXML_NOERROR:NULL)) 
	{
		$hasPK1 = false;
		$hasPK2 = false;
		$hasPK3 = false;
		$hasPK4 = false;
				
		foreach($xml->Content->Bilanz->Spieler as $key => $attribute) 
		{
			if ($attribute->PK1) { $hasPK1 = true; }
			if ($attribute->PK2) { $hasPK2 = true; }
			if ($attribute->PK3) { $hasPK3 = true; }
			if ($attribute->PK4) { $hasPK4 = true; }			
		}
		
		$plan = '<table class=\''.$tableclassname.'\'>';
		$plan .= "<thead>\n";
		$plan .= "<th style='text-align: center;'>Pos</th>\n";
		$plan .= "<th>Spieler</th>\n";
		$plan .= "<th>Bem.</th>\n";
		$plan .= "<th style='text-align:center;'>ST</th>\n";
		
		if ($hasPK1) { $plan .= "<th style='text-align:center;'>PK1</th>"; }
		if ($hasPK2) { $plan .= "<th style='text-align:center;'>PK2</th>"; }
		if ($hasPK3) { $plan .= "<th style='text-align:center;'>PK3</th>"; }
		if ($hasPK4) { $plan .= "<th style='text-align:center;'>PK4</th>"; }
		
		$plan .= "<th>Bilanz</th>\n";
		$plan .= "<th>LivePZ</th>\n";
		$plan .= "</tr></thead>\n";

		$zeile = 0;
		foreach($xml->Content->Bilanz->Spieler as $key => $attribute) 
		{
			$zeile++;			
			$plan .= "<tr";
			if ($zeile % 2 !=0) {
				//ungerade
				$plan .=  " class='even'>";
			} else {
				//gerade
				$plan .=  " class='odd'>";
			}
			$plan .= "<td style='text-align: center;'>".$attribute->Position ."</td>\n";
			$plan .= '<td><a href="'.$params['baseurl'].'/default.aspx?L1=Ergebnisse&L2=TTStaffeln&L2P='.$params['staffel_id'].'&L3=Spieler&L3P='.$attribute->ID.'" target="_blank">'.$attribute->Spielername ."</a></td>\n";
			$plan .= "<td>".trim($attribute->Attribute) ."</td>\n";
			$plan .= "<td style='text-align:center;'>".$attribute->Teilnahme ."</td>\n";
			if ($hasPK1) { $plan .= "<td style='text-align:center;'>".$attribute->PK1 ."</td>\n"; };
			if ($hasPK2) { $plan .= "<td style='text-align:center;'>".$attribute->PK2 ."</td>\n"; };
			if ($hasPK3) { $plan .= "<td style='text-align:center;'>".$attribute->PK3 ."</td>\n"; };
			if ($hasPK4) { $plan .= "<td style='text-align:center;'>".$attribute->PK4 ."</td>\n"; };
			$bilanz = "";
			if ($attribute->GesamtPlus) {
				$bilanz = $attribute->GesamtPlus .":".$attribute->GesamtMinus;
			}
			$plan .= "<td style='text-align:center;'>".$bilanz."</td>\n";
			$plan .= "<td>".$attribute->LivePZ ."</td>\n";
			$plan .= "</tr>\n";
		}
		$plan .= "</table>";
		return $plan;
	} 
	else 
	{
		return 'Konnte TT-Live-XML nicht laden';
	}	 
}

function TTLive_install() {
	add_option("TTLive_baseurl", 'http://bettv.tischtennislive.de', '', 'yes');
	add_option("TTLive_refreshHours", '2', '', 'yes');
	add_option("TTLive_divisionID", '', '', 'yes');
	add_option("TTLive_tableclassname_TeamSpielplan", 'TTLiveSpielplan', '', 'yes');
	add_option("TTLive_tableclassname_Tabelle", 'TTLiveTabelle', '', 'yes');
	add_option("TTLive_tableclassname_14Tage", 'TTLive14Tage', '', 'yes');
	add_option("TTLive_tableclassname_Mannschaft", 'TTLiveMannschaft', '', 'yes');
    add_option("TTLive_tableclassname_Rangliste", 'TTLiveRangliste', '', 'yes');
    add_option("TTLive_tableclassname_Hallenplan", 'TTLiveHallenplan', '', 'yes');
}

function TTLive_remove() {
	delete_option('TTLive_baseurl');
	delete_option('TTLive_refreshHours');
	delete_option('TTLive_divisionID');
	delete_option('TTLive_tableclassname_TeamSpielplan');
	delete_option('TTLive_tableclassname_Tabelle');
	delete_option('TTLive_tableclassname_14Tage');
	delete_option('TTLive_tableclassname_Mannschaft');
    delete_option('TTLive_tableclassname_Rangliste');
    delete_option('TTLive_tableclassname_Hallenplan');
}

if ( is_admin() ){

	/* Call the html code */
	add_action('admin_menu', 'TTLive_admin_menu');

	function TTLive_admin_menu() {
		add_options_page('TTLive', 'TTLive', 'administrator','TTLive', 'TTLive_html_page');
	}
}

function TTLive_html_page() {
?>
<div>
<h2>TTLive Settings</h2>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>

<table width="800" class="form-table">
<tr><td colspan=2 style="border-top:solid 1px lightgrey;"><b>Options:</b></td></tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_baseurl">Base Url</label></th>
<td>
<input name="TTLive_baseurl" style="width: 350px;" type="text" id="TTLive_baseurl"
value="<?php echo get_option('TTLive_baseurl'); ?>" />
<br />
<i>Base URL to your TTLive-System (ex. http://bettv.tischtennislive.de)</i>
</td>
</tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_refreshHours">Refresh hours</label></th>
<td>
<input name="TTLive_refreshHours"  style="width: 50px;" type="text" id="TTLive_refreshHours"
value="<?php echo get_option('TTLive_refreshHours'); ?>" />
Stunden
<br />
Hours until data from TT-live-system will be refreshed (ex. 1)
</td>
</tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_divisionID">Abteilungs/SpartenID</label></th>
<td>
<input name="TTLive_divisionID"  style="width: 100px;" type="text" id="TTLive_divisionID" value="<?php echo get_option('TTLive_divisionID'); ?>" />
<br />
Ihr bekommt diese und andere ID´s im TischtennisLive-System aus der URL der XML-Dateien unter Verwaltung --> Statistiken
</td>
</tr>
<tr><td colspan=2 style="border-top:solid 1px lightgrey;"><b>Style:</b></td></tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_tableclassname_TeamSpielplan">CSS-Class TeamSpielplan</label></th>
<td>
<input name="TTLive_tableclassname_TeamSpielplan"  style="width: 350px;" type="text" id="TTLive_tableclassname_TeamSpielplan"
value="<?php echo get_option('TTLive_tableclassname_TeamSpielplan'); ?>" />
</td>
</tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_tableclassname_Tabelle">CSS-Class Tabelle</label></th>
<td>
<input name="TTLive_tableclassname_Tabelle"  style="width: 350px;" type="text" id="TTLive_tableclassname_Tabelle"
value="<?php echo get_option('TTLive_tableclassname_Tabelle'); ?>" />
</td>
</tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_tableclassname_14Tage">CSS-Class 14Tage</label></th>
<td>
<input name="TTLive_tableclassname_14Tage"  style="width: 350px;" type="text" id="TTLive_tableclassname_14Tage"
value="<?php echo get_option('TTLive_tableclassname_14Tage'); ?>" />
</td>
</tr>
<tr valign="middle">
<th scope="row"><label for="TTLive_tableclassname_Mannschaft">CSS-Class Mannschaft</label></th>
<td>
<input name="TTLive_tableclassname_Mannschaft"  style="width: 350px;" type="text" id="TTLive_tableclassname_Mannschaft"
value="<?php echo get_option('TTLive_tableclassname_Mannschaft'); ?>" />
</td>
</tr>
    <tr valign="middle">
        <th scope="row"><label for="TTLive_tableclassname_Rangliste">CSS-Class Rangliste</label></th>
        <td>
            <input name="TTLive_tableclassname_Rangliste"  style="width: 350px;" type="text" id="TTLive_tableclassname_Rangliste"
                   value="<?php echo get_option('TTLive_tableclassname_Rangliste'); ?>" />
        </td>
    </tr>
    <tr valign="middle">
        <th scope="row"><label for="TTLive_tableclassname_Hallenplan">CSS-Class Hallenplan</label></th>
        <td>
            <input name="TTLive_tableclassname_Hallenplan"  style="width: 350px;" type="text" id="TTLive_tableclassname_Hallenplan"
                   value="<?php echo get_option('TTLive_tableclassname_Hallenplan'); ?>" />
        </td>
    </tr>
</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="TTLive_baseurl, TTLive_refreshHours, TTLive_divisionID, TTLive_tableclassname_TeamSpielplan, TTLive_tableclassname_Tabelle, TTLive_tableclassname_14Tage, TTLive_tableclassname_Mannschaft, TTLive_tableclassname_Rangliste, TTLive_tableclassname_Hallenplan" />

<p>
<input type="submit" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div>
<?php
}

register_activation_hook(__FILE__,'TTLive_install'); 

register_deactivation_hook( __FILE__, 'TTLive_remove' );

/**
 * Adds TTLive_Widget widget.
 */
class TTLive_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
	 		'ttlive_widget', // Base ID
			'TTLive_Widget', // Name
			array( 'description' => __( 'TTLive Widget', 'text_domain' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$maxnumber = apply_filters( 'widget_title', $instance['maxnumber'] );
		$showxdays = apply_filters( 'widget_title', $instance['showxdays'] );
		$title_results = apply_filters( 'widget_title', $instance['title_results'] );
		$title_preview = apply_filters( 'widget_title', $instance['title_preview'] );
        echo '<aside class="widget widget-ttlive">';
		if ( ! empty( $title ) ) {
			echo '<h1 class="widget-title">'.$title.'</h1>';
		}
		$header = "<em>$title_results</em>";
		$arr = array("elementname" => "14Tage", "display_type" => "0", "widget" => "1", "showxdays" => $showxdays, "max" => $maxnumber);
		if ( empty($maxnumber) ) unset($arr["max"]);
		if ( empty($showxdays) ) unset($arr["showxdays"]);		
		$tmp = ttlive_func($arr,null);
        if ($tmp != "") echo $header.$tmp;
		$arr["display_type"] = "1";
		$title_preview = "<em>$title_preview</em>";
        $tmp_prev = ttlive_func($arr,null);
		if ($tmp_prev != "") echo $title_preview.$tmp_prev;
        echo '</aside>';
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['title_results'] = strip_tags( $new_instance['title_results'] );
		$instance['title_preview'] = strip_tags( $new_instance['title_preview'] );
		$instance['showxdays'] = strip_tags( $new_instance['showxdays'] );
		$instance['maxnumber'] = strip_tags( $new_instance['maxnumber'] );

		return $instance;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'Neuer Titel', 'text_domain' );
		}
		if ( isset( $instance[ 'title_results' ] ) ) {
			$title_results = $instance[ 'title_results' ];
		}
		else {
			$title_results = __( 'Ergebnisse', 'text_domain' );
		}
		if ( isset( $instance[ 'title_preview' ] ) ) {
			$title_preview = $instance[ 'title_preview' ];
		}
		else {
			$title_preview = __( 'Vorschau', 'text_domain' );
		}
		if ( isset( $instance[ 'showxdays' ] ) ) {
			$showxdays = $instance[ 'showxdays' ];
		}
		else {
			$showxdays = __( '', 'text_domain' );
		}
		if ( isset( $instance[ 'maxnumber' ] ) ) {
			$maxnumber = $instance[ 'maxnumber' ];
		}
		else {
			$maxnumber = __( '', 'text_domain' );
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<label for="<?php echo $this->get_field_id( 'title_results' ); ?>"><?php _e( 'Titel Ergebnisse:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title_results' ); ?>" name="<?php echo $this->get_field_name( 'title_results' ); ?>" type="text" value="<?php echo esc_attr( $title_results ); ?>" />
		</p>
		<label for="<?php echo $this->get_field_id( 'title_preview' ); ?>"><?php _e( 'Title Vorschau:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title_preview' ); ?>" name="<?php echo $this->get_field_name( 'title_preview' ); ?>" type="text" value="<?php echo esc_attr( $title_preview ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'showxdays' ); ?>"><?php _e( 'Anzahl der Tage:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'showxdays' ); ?>" name="<?php echo $this->get_field_name( 'showxdays' ); ?>" type="text" value="<?php echo esc_attr( $showxdays ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'maxnumber' ); ?>"><?php _e( 'Max. Spiele:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'maxnumber' ); ?>" name="<?php echo $this->get_field_name( 'maxnumber' ); ?>" type="text" value="<?php echo esc_attr( $maxnumber ); ?>" />
		</p>
		<?php 
	}

} // class TTLive_Widget
// register TTLive_Widget widget
add_action( 'widgets_init', create_function( '', 'register_widget( "ttlive_widget" );' ) );

add_shortcode( 'ttlive', 'TTLive_func' );
?>