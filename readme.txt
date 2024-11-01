=== TTLive ===
Contributors: finalan
Donate link:
Tags: 
Requires at least: 3.0.1
Tested up to: 4.7.3
Stable tag: 0.9.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple wordpress plugin to get the data from the ttlive-system and display in your wp-post or wp-page with shortcode.

== Description ==

A simple wordpress plugin to get the data from the ttlive-system and display in your wp-post or wp-page with shortcode. You can also use a widget to show the match-results and preview in a sidebar.

Tischtennislive is a german online sport result system (http://www.tischtennislive.de). TTLive-Plugin help you, to show current team results on your WP-Page or WP-Posts. 

Mit Hilfe dieses Plugins kann man die aktuellen Ergebnisse und Daten vom Tischtennislive-System ziehen und auf seiner eigenen Vereinsseite darstellen.

Es können die Elemente Mannschaft, Spielplan, Tabelle und 14Tage angezeigt werden. Dabei aktualisiert dieses Plugin regelmäßig die Daten und speichert die entsprechende XML-Datei.

== Installation ==

1. Download and unzip `ttlive`-zip-file
2. Upload `ttlive`-directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Set base url in admin settings/options.
5. Place `[ttlive]` shortcode in your posts or pages. Use parameter to select which data you will show.

Parameters:
`elementname` - Rueckgabe-Element - mögliche Werte: Mannschaft, Spielplan, Tabelle, 14Tage oder Rangliste
`mannschaft_id` - TTLive Mannschaft ID
`staffel_id` - TTLive Staffel ID
`tableclassname` - css-Klassenname der Tabelle
`own_team` -  Name des eigenen Teams
`runde` -  Vorrunde = [ttlive runde=1] (default), Rückrunde = [ttlive runde=2]
`showxdays` -  14Tage: Anzahl der Tage die dargestellt werden sollen (default = 14)
`max` -	 14Tage: Anzahl der Tage die maximal dargestellt werden sollen (default = 0)
`widget` -  14Tage: Für die Darstellung in einem Widget - default = 0 --> legt man den Schalter auf 1, wird eine Darstellung optimiert für ein Sidebar-Widget verwendet 
`teamalias` - Nur für die Tabelle: "Teamname:Alias;Teamname2:Alias2;..."
`showleague` - Nur für die Tabelle: Ueberschrift-Anzeige der Liga (default: 1)
`showmatchecount` - Nur für die Tabelle: Anzahl der gemachten Spiele (default: 1)
`showsets` - Nur für die Tabelle:  Anzahl der gewonnenen/verlorenen Saetze (default: 1)
`showgames` - Nur für die Tabelle:  Anzahl der gewonnenen/verlorenen Spiele (default: 1)
`aufstiegsplatz` - Nur für die Tabelle:  Aufstiegsplaetze bis (default: 2)
`abstiegsplatz` - Nur für die Tabelle:  Abstiegsplaetze ab (default: 9)
`relegation` - Nur für die Tabelle:  Relegationsplätze (default: '') Beispiel: relegation="2,8" -> 2 für die Relegation Aufstieg, und 8 für Abstieg
`saison` - Nur für Hallenplan: (default: '') Wenn '', dann wird kein Hallenplan angezeigt. Das erste Jahr der Saison muss hier gesetzt werden.
`display_type` - Nur für Rangliste: (default: 1) Wenn 0, dann werden nur die Spieler angezeigt die eine gültige LivePZ haben
`display_type` - Nur für die 14Tage: die letzten 14Tage (0 - default) oder die naechsten 14Tage (1)		
`refresh` - Anzahl Stunden bis die Daten erneut vom Live-System aktualisiert werden sollen

Examples with base url (http://bettv.tischtennislive.de):
`[ttlive elementname="Mannschaft" mannschaft_id="25340" staffel_id="3904"]`
`[ttlive elementname="Spielplan" own_team="SV Berliner Brauerei" mannschaft_id="25340" staffel_id="3904" tableclassname="TTLiveSpielplan"]`
`[ttlive elementname="Tabelle" own_team="SV Berliner Brauereien e.V." mannschaft_id="25340" staffel_id="3904" tableclassname="TTLiveTabelle" aufstiegsplatz=2 abstiegsplatz=9 relegation="2,8" teamalias="SV Berliner Brauereien e.V.:SVBB1; SV Lichtenberg 47:Lichtenberg"]`
`[ttlive elementname="14Tage" tableclassname="TTLive14Tage" display_type=0]`
`[ttlive elementname="14Tage" tableclassname="TTLive14Tage" display_type=1]`
`[ttlive elementname="Rangliste" tableclassname="TTLiveRangliste" display_all=0]`
`[ttlive elementname="Hallenplan" saison="2014" runde="1"]`

css Example:
`.even { background-color: whitesmoke; }
.cAufstieg{ color:green; }
.cAbstieg{ color:red; }
.cRelegation{ color:DarkOrange; }
.cOwnTeam td { font-weight: bold; }
.cOwnTeam:hover{
	background-color: #ddd;
	cursor:pointer;
}
.TTLiveRangliste { width: 100%; }
.TTLive14Tage { width: 100%; }
.TTLiveTabelle { width: 100%; }
.TTLiveMannschaft { width: 100%; }
.TTLiveHallenplan { width: 100%; }
.TTLiveSpielplan { width: 100%; }
th { text-align: left; }` 

== Frequently Asked Questions ==

= Where i can i find this plugin in action? =

Check the [plugin homepage] (http://www.svbb-tischtennis.de/).

== Screenshots ==

no screenshots available

== Changelog ==

= 0.9.7 =
* fixed a bug and several php warnings/notice

= 0.9.6 =
* because of different errors --> back to 0.9.3; sorry, for that!

= 0.9.5 =
* changed Tested up to 4.7.3

= 0.9.4 =
* new Options to set global field names "mannschafts id" and "staffel id" for team-pages to work with ACF-Plugin or user-defined fields
* calculates the difference in "Rangliste" between LivePZ and Stichtagen

= 0.9.3 =
* bugfix: calculated color in TTLive Widget, Spielplan, Hallenplan

= 0.9.2 =
* added new elementname: Hallenplan - displays Hallenplan (example: [ttlive elementname="Hallenplan" saison="2014" runde="1"])
* new parameter for element "Hallenplan": saison (default: '') - saison is the current year (only first year "2014" - NOT "2014/2015")
* added tableclassname in configuration for elementname Rangliste and Hallenplan

= 0.9.1 =
* added new elementname: Rangliste - displays Rangliste by LivePZ (example: [ttlive elementname="Rangliste"])
* new parameter for element "Rangliste": display_all (default: 1) - display_all=0, hides players with LivePZ "k.A."
* bugfix in widget: if there is no value to display, the header will be hidden

= 0.9 =
* changed from LPZ to LivePZ
* bugfix: if there is no PK1 or PK2 in Team-Table, it will be hidden

= 0.8.5 =
* bugfix: added "Mini" as possible own team to display color in 14Tage

= 0.8.4 =
* bugfix: if search&replace is not replacing anything in the league-name, then the full league-name is been displayed 

= 0.8.3 =
* added sorting for last matches, to show the max number of matches by date 

= 0.8.1 =
* bugfix: Error in 14Tage: PHP Class DateInterval is only available with PHP Version 5.3 and higher - Problem fixed with a workaround

= 0.8 =
* added new parameter showxdays --> 14Tage: Anzahl der Tage die dargestell werden sollen (default = 14)
* added new parameter max --> 14Tage: Anzahl der Tage die maximal dargestellt werden sollen (default = 0)
* added TTLive_Widget --> you can use the widget to show the 14Tage-View in a sidebar

= 0.7 =
* bugfix: classes even and odd - to show tablerows in different colors
* added new parameter relegation, for displaying relegation-position in a different color
* changed from hardcoded colors for aufstieg, abstieg, relegation and own-team to css-classes (class-names: cAufstieg, cAbstieg, cRelegation, cOwnTeam)


= 0.6 =
* added new parameter runde --> Vorrunde = [ttlive runde=1] (default), Rückrunde = [ttlive runde=2]
* added Abteilungs/SpartenID as option in TTLive-settings for the 14Tage-Function 

= 0.5 =
* added tableclassname as default-value-setting in TTLive-options for Mannschaft, 14Tage, Tabelle and Spielplan (you don't need to add these parameters to your shortcode, but if you do, it will override setting)
* custom fields support for default values - if you add custom fields "mannschaft_id" or "staffel_id" to a team page, then it will be the default-value (you don't need to add these parameters to your shortcode, but if you do, it will override the custom field)
* added description for using the teamalias-parameter 

= 0.4 =
* changed error

= 0.1 =
* Initial version
