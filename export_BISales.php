<?php

defined('POINTADMINURL') or define('POINTADMINURL',"/modules/callcenter/pointsystem_admin");

include_once dirname($_SERVER["SCRIPT_FILENAME"]) . '/securator/securator.util_db.php';
$utilDB = new SECAdminDB;

set_time_limit(3600);
ini_set("memory_limit","1024M");

AccountMustBeOnline(ACCESS_CALLCENTER, 'CALLCENTER');

function toDecimal($val) {

	// Convert any exponential sign to proper decimals
	$val = sprintf("%lf", $val);

	if (strpos($val, '.') !== false) {
		$val = rtrim(rtrim($val, '0'), '.');
	}

	return $val;
}

function Days_diff($d1,$d2,$t){
            
$date1 = new DateTime($d1);
$date2 = new DateTime($d2);

switch($t) {
            case('d'): $f =   1; break;
            case('m'): $f =  30; break;
            case('q'): $f =  90; break;
            case('y'): $f = 365; break;
}
$diff = $date1->diff($date2);

return floor(abs($diff->format('%a')/$f));
}

function Price_Group($price){
	if($price >=    0 and $price <= 3499) { $group =     '0-3499'; }
	if($price >= 3500 and $price <= 7499) { $group =  '3500-7499'; }
    if($price >= 7500)                    { $group = '7500-20000'; } 
    return $group; 	
}

function calculateSaleType($d)
{
	$res = '';

	if ($d['online_purchase'] == 'Paid')
		$res = "Net";
	else
	if ($d['pool_id'] > 0)
		$res = "Pulje";
	else

	if ($d['insurance_pool'] == 1)
		$res = "Opsalg";
	else
	// Hylde
	{
		$temp = $d['temporary_uniqid'];
		if ($temp)
		{
			$parts = explode('-', $temp);
			if (count($parts) == 3 || substr($temp, 0, 6) == "RETURN" || substr($temp, 0, 6) == "KREDIT")
			{
				$acc = db_get("
					SELECT 1 from
						pool_storage_list
					where
						shortnames = '".$parts[0]."' and
						colli_id = '".$parts[1]."' and
						label_id = '".$parts[2]."'
				");
				if ( $acc || substr($temp, 0, 6) == "RETURN" || substr($temp, 0, 6) == "KREDIT" || preg_match('/[a-zA-Z]-[0-9]{5}-[0-9]{8}/',$temp) )
					$res = "Hylde";
			}
		}
	}

	if ($res == '')
		$res = "Alm";

	$res .= ";";

	return $res;
}

function stdHeader($header)
{
	if (empty($header))
		return;
	echo '
			<table class="securator_box" cellspacing="0" cellpadding="0" width="100%" >
			<tr class="securator_box_top_row">
				<td class="securator_box_top_left">&nbsp;</td>
				<td class="securator_box_top_center" nowrap>' . $header . '</td>
				<td class="securator_box_top_right">
				</td>
			</tr>
			</table>';
}

function drawMenuSelectors($startdate, $enddate, $chainSelected, $chainArray, $statusSelected, $productClasses, $prodclassSelected, $csvColumns)
{
	echo '
		<link rel="stylesheet" type="text/css" href="' . POINTADMINURL . '/datepickr.css" />
      	<form id="form1" name="form1" method="post" action="">
          <table border="0" cellspacing="0" cellpadding="0">
	';
	echo '<tr><td><div style="width:100px;"><strong>Startdato</strong></div></td><td>';
	echo '<input style="width:100px;" type="text" name="startDate" id="startDate" value="' . $startdate . '" />';

	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">
			         new datepickr(\'startDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'s�ndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'l�rdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';
	echo "</td></tr>";

	echo '<tr><td><div style="width:100px;"><strong>Slutdato</strong></div></td>';
	echo '<td>';
	echo '<input style="width:100px;" type="text" name="endDate" id="endDate" value="' . $enddate . '" />';
	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">
			         new datepickr(\'endDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'s�ndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'l�rdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';
	echo '</td></tr>';

	echo '<tr><td><div style="width:100px;"><strong>K�de</strong></div></td>';
	echo '<td><select name="shopchain" style="width:150px;">
		<option value="">V�lg k�de</option>';
	foreach ($chainArray as $curChain) {
		echo '<option value="' . $curChain['rec_id'] . '"';
		if (!empty($chainSelected) && $chainSelected == $curChain['rec_id'])
			echo 'selected="selected"';
		echo '>' . $curChain['name'] . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><td><div style="width:100px;"><strong>Klasse</strong></div></td>';
	echo '<td><select name="productclass" style="width:150px;">
		<option value="">V�lg klasse</option>';
	foreach ($productClasses as $curPC)
	{
		echo '<option value="' . $curPC['name'] . '"';
		if (!empty($prodclassSelected) && $prodclassSelected == $curPC['name'])
			echo 'selected="selected"';
		echo '>' . $curPC['name'] . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><td><div style="width:100px;"><strong>Udtr�k efter</strong></div></td>';
	echo '<td><select name="sortby" style="width:150px;">';
	if (@$statusSelected == 1)
		$sel = 'selected="selected"';
	else
		$sel = "";
	echo '<option value="1" ' . $sel . '>Oprettelsesdato</option>';
	if (@$statusSelected == 2)
		$sel = 'selected="selected"';
	else
		$sel = "";
	echo '<option value="2" ' . $sel . '>Aktiveringsdato</option>';
	if (@$statusSelected == 3)
		$sel = 'selected="selected"';
	else
		$sel = "";
	echo '<option value="3" ' . $sel . '>Annulleringsdato</option>';
	echo '</select></td>';
	echo '</tr>';
	echo '<tr><td><div style="width:100px;"><strong>Udv�lg kolonner til export</strong></div></td>';
	echo '<td><select name="colsel[]" multiple style="width:150px;">';
	echo '<option value="-Alle-" selected>-Alle-</option>';
	$cols = explode(";", $csvColumns);
	foreach ($cols as $col) {
		if (!empty($col))
			echo '<option value="' . $col . '">' . $col . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr>
              <td>&nbsp;</td>
              <td align="right">
              <input type="submit" name="button1" id="button1" value="Hent" /></td>
              </tr>
		</table>
	</form>
	';
}
/////////////////////////////////////////////////////////////////////////////////

$start_date = "";
$end_date = "";
$csvColumns = "startdato;slutdato;alder;policenummer;rec_id;kontoid;navn;adresse;model;serieno;koebspris;gruppe;praemie;forhandler;loebetid;forsikringsstatus;";
$csvColumns .= "gruppenavn;klasse;producent;sagsnavn;erhvervskunde;oprettelsesdato;saelgeravance;salgstype;kaede;antal;saelger;";
$csvColumns .= "annulleringsdato;annulleringsgrund;forsikringstype;kategori;aktiveringsdato;d�kningstype;kontant;betaltibutik;";
$csvColumns .= "saelgerregdato;mail;gade;postnr;by;lel;risikoklasse;";
$csvColumns .= "frontingfee;skadeforsikringsafgift;salgsomkostning;aeskeomkostning;";
$csvColumns .= "saelgerprovision;sikkernet_support;risikopraemie;risikopraemie2;allriskpraemie;";

if (isset($_POST['sortby']))
{
	if(@$_POST["sortby"] == "2")
		$sortbyfield = "`activation_date`";
	elseif (@$_POST['sortby'] == "3")
		$sortbyfield = "`CanceledDate`";
	else
		$sortbyfield = "`create_date`";
	if (!empty($_POST['startDate']))
	{
		$myDateTime = DateTime::createFromFormat('d-m-Y', $_POST['startDate']);
		$ymdStartDate = $myDateTime->format('Y-m-d');

		if (!empty($ymdStartDate))
			$start_date = "AND ".$sortbyfield." >= '".$ymdStartDate."' ";
	}

	if (!empty($_POST['endDate']))
	{
		$myDateTime = DateTime::createFromFormat('d-m-Y', $_POST['endDate']);
		$ymdEndDate = $myDateTime->format('Y-m-d');

		if (!empty($ymdStartDate))
			$end_date = "AND ".$sortbyfield." <= '".$ymdEndDate."' ";
	}
	if (($start_date == "") || ($end_date == ""))
	{
		$messages->Error('callcenter', 'export_dates_must_be_set');
	}
	else
	{
		if(@$_POST["group_id"])
		{
			$group_joins = "
				JOIN
					`securator_dk`.swc_account sa ON sa.account_id=pip.dealer_id and sa.groups = concat(',', ".@$_POST["group_id"].", ',')
				JOIN
					`securator_dk`.swc_account_groups sag ON sag.rec_id = ".@$_POST["group_id"];
		}
		else
		{
			$group_joins = "
				left JOIN
					`securator_dk`.swc_account sa ON sa.account_id=pip.dealer_id
				left JOIN
					`securator_dk`.swc_account_groups sag ON sa.groups = concat(',', sag.rec_id, ',')
			";
		}
		if (!empty($_POST['shopchain']))
		{
			$dealerIds = $utilDB->getDealerIdsFromChain($_POST['shopchain']);
			$whereDealerIds = "AND pip.dealer_id IN (" . $dealerIds . ") ";
		}
		else
		{
			$whereDealerIds = "";
		}

		$sql = "
			SELECT pip.dealer_id,pip.account_id,pip.product_group_id,pip.start_date,pip.end_date,pip.policy_number,pip.rec_id,pip.manufacturer_id,pip.product_model,
				pip.serial_no,pip.purchase_price,pip.policy_price,pip.status,pip.case_id,pip.business,pip.create_date,pip.temporary_uniqid,pip.dealer_profit,
				pip.CanceledDate,pip.CanceledText,pip.insurance_group_id,pip.dealer_user_id,pip.activation_date,pip.pay_type,
				pip.shelf_user_assigned,pip.lel,pip.online_purchase,pip.pool_id,
				period_diff(end_date,start_date) as per,
				insurance_pool,
				sag.name as 'swc_account_groups_name',
				`swc_account_users`.`user_name` AS usr,
				`Payment_Services`.paid
			FROM
				`securator_dk`.products_insurance_policies pip
			LEFT JOIN
				`securator_dk`.`swc_account_users` ON `swc_account_users`.`user_id`=pip.dealer_user_id
			LEFT JOIN
				`securator_dk`.`Payment_Services` ON `Payment_Services`.`insurance`=pip.rec_id
			LEFT JOIN
				`securator_dk`.products_insurance_group on insurance_group_id = group_id
			$group_joins
			WHERE
				(online_purchase='No' OR online_purchase='Paid') and pip.policy_number <> '' $whereDealerIds $start_date $end_date ORDER BY rec_id
		";

		// Pr�-indl�sning af annulleringstekster..
		$cancelQuery = $utilDB->secDB->db_fetch_array("SELECT id, text FROM `cancel_texts` ");
		$cancelTexts = array(0 => "-");
		if ($cancelQuery)
		{
			foreach($cancelQuery as $cq)
			{
				$cancelTexts[$cq['id']] = $cq['text'];
			}
		}

		// Finder forsikringstype (FULLCARE, ALLCARE, m.m.)
		$insurance_types_fetch = $utilDB->secDB->db_fetch_array("
			SELECT
				`pig`.`group_id`, `pic`.`name`
			FROM
				`securator_dk`.products_insurance_group `pig`
			LEFT JOIN
				`securator_dk`.products_insurance_coverage `pic` ON `pic`.`id`=`pig`.`coverage_id`;
		");
		foreach ($insurance_types_fetch as $itf)
		{
			$insurance_types[$itf["group_id"]] = $itf["name"];
		}

		$insurance_group_fetch = $utilDB->secDB->db_fetch_array("
			SELECT
				pg.`group_id`, pg.`name`, pc.name2
			FROM
				`securator_dk`.`products_insurance_group` pg
			LEFT JOIN
				`securator_dk`.products_insurance_coverage pc ON pg.coverage_id=pc.id;
		");
		foreach ($insurance_group_fetch as $igf)
		{
			$insurance_group_names[$igf["group_id"]] = $igf["name"];
			$insurance_coverage_names[$igf["group_id"]] = $igf["name2"];
		}

		$res = "";
		foreach (explode(";", $csvColumns) as $curCol)
		{
			if ((in_array($curCol,$_POST['colsel']) && $curCol != "-Alle-") || in_array("-Alle-",$_POST['colsel']))
				$res .= $curCol . ";";
		}
		$res .= "\n";

		$myFile = "/opt/export/export.csv";
		$fh = fopen($myFile, 'w+') or die("can't open file");
		fwrite($fh, $res);
		$res = null;

		$query = mysql_query($sql);

		while(($d = mysql_fetch_assoc($query)) != FALSE)
		{
			$acc = $utilDB->secDB->db_get("
				SELECT
					name,company, street_address, postcode, city
				FROM
					`securator_dk`.swc_account_address_book
				WHERE
					account_id = '".$d['account_id']."' AND
					default_addr = 1
			");
			$grp = $utilDB->secDB->db_get("
				SELECT
					prc.risk_name, pg.name AS group_name, pc.name AS class_name
				FROM
					products_group pg
				LEFT JOIN
					products_class pc ON pc.class_id = pg.class_id
				LEFT JOIN
					products_risk_class prc ON prc.risk_id = pg.risk_id
				WHERE
					pg.group_id = '".$d['product_group_id']."';
			");
			if (!empty($_POST['productclass']) && $_POST['productclass'] != $grp['class_name'])
			{
				continue;
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("startdato",$_POST['colsel']))
				$res .= '"'.Date('Y-m-d',strtotime($d['start_date'])).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("slutdato",$_POST['colsel']))
				$res .= '"'.Date('Y-m-d',strtotime($d['end_date'])).'";';
			
			$mindate = min($d['end_date'],date('Y-m-d'));
			$res .= '"'.Days_diff($d['start_date'],$mindate,'y').'";';
						
			if (in_array("-Alle-",$_POST['colsel']) || in_array("policenummer",$_POST['colsel']))
				$res .= '"'.$d['policy_number'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("rec_id",$_POST['colsel']))
				$res .= '"'.$d['rec_id'].'";';

			$acc1 = $acc;

			if (in_array("-Alle-",$_POST['colsel']) || in_array("kontoid",$_POST['colsel']))
				$res .= '"'.$d['account_id'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("navn",$_POST['colsel']))
			{
				$res .= '"'.str_replace('"','',$acc['name']);
				if ($acc['name'] != '' && $acc['company'] != '')
					$res .= ' / ';

				$res .= str_replace('"','',$acc['company']).'";';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("adresse",$_POST['colsel']))
				$res .= '"'. str_replace("\n", ",", $acc['street_address'].' '.$acc['postcode'].' '.$acc['city']) .'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("model",$_POST['colsel']))
				$res .= '"'.str_replace('"', "'",$d['product_model']).'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("serieno",$_POST['colsel']))
				$res .= '"'.$d['serial_no'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("koebspris",$_POST['colsel']))
				$res .= '"'.MoneyFormat($d['purchase_price']).'";';
			
			$res .= '"'.Price_Group($d['purchase_price']).'";';			
			
			if (in_array("-Alle-",$_POST['colsel']) || in_array("praemie",$_POST['colsel']))
				$res .= '"'.MoneyFormat($d['policy_price']).'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("forhandler",$_POST['colsel']))
			{
				$acc = $utilDB->secDB->db_get("SELECT name FROM swc_account_address_book WHERE account_id=".$d['dealer_id']." AND default_addr=1");
				$res .= '"'.$acc['name'].'";';
			}
			if (in_array("-Alle-",$_POST['colsel']) || in_array("loebetid",$_POST['colsel']))
				$res .= '"'.($d['per']/100).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("forsikringsstatus",$_POST['colsel']))
				$res .= '"'.$d['status'].'";';

			if (!empty($grp))
			{
				if (in_array("-Alle-",$_POST['colsel']) || in_array("gruppenavn",$_POST['colsel']))
					$res .= '"'.$grp['group_name'].'";';
				if (in_array("-Alle-",$_POST['colsel']) || in_array("klasse",$_POST['colsel']))
					$res .= '"'.$grp['class_name'].'";';
			}
			else
			{
				if (in_array("-Alle-",$_POST['colsel']) || in_array("gruppenavn",$_POST['colsel']))
					$res .= '"";';
				if (in_array("-Alle-",$_POST['colsel']) || in_array("klasse",$_POST['colsel']))
					$res .= '"";';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("producent",$_POST['colsel']))
			{
				$man = $utilDB->secDB->db_get("SELECT name FROM products_manufactures WHERE rec_id=".$d['manufacturer_id']." ");
				$res .= '"'.$man['name'].'";';
			}

			if ($d['case_id'] > 0)
			{
				if (in_array("-Alle-",$_POST['colsel']) || in_array("sagsnavn",$_POST['colsel']))
				{
					$case = $utilDB->secDB->db_get("SELECT name FROM products_insurance_case WHERE rec_id='".$d['case_id']."'");
					$res .= '"'.$case['name'].'";';
				}
			}
			else
			{
				if (in_array("-Alle-",$_POST['colsel']) || in_array("sagsnavn",$_POST['colsel']))
					$res .= '"";';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("erhvervskunde",$_POST['colsel']))
			{
				if ($d['business'] == 0)
					$res .= '"Nej";';
				else
					$res .= '"Ja";';
			}

			// Oprettelsesdato
			if (in_array("-Alle-",$_POST['colsel']) || in_array("oprettelsesdato",$_POST['colsel']))
				$res .= '"'.DateFormat($d['create_date']).'";';

			// S�lgeravance
			if (in_array("-Alle-",$_POST['colsel']) || in_array("saelgeravance",$_POST['colsel']))
			{
				if (substr($d["temporary_uniqid"], 0, 6) == "KREDIT")
				{
					if (MoneyFormat($d['dealer_profit']) < 0)
						$res .= '"'.MoneyFormat($d['dealer_profit']).'";';
					else
						$res .= '"-'.MoneyFormat($d['dealer_profit']).'";';
				}
				else if (substr($d["temporary_uniqid"], 0, 6) == "RETURN")
				{
					if (MoneyFormat($d['dealer_profit']) < 0)
						$res .= '"'.substr(MoneyFormat($d['dealer_profit']), 1).'";';
					else
						$res .= '"'.MoneyFormat($d['dealer_profit']).'";';
				} else {
					$res .= '"'.MoneyFormat($d['dealer_profit']).'";';
				}
			}

			// Salgstype
			if (in_array("-Alle-",$_POST['colsel']) || in_array("salgstype",$_POST['colsel']))
				$res .= calculateSaleType($d);

			// K�de
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kaede",$_POST['colsel']))
				$res .= $d['swc_account_groups_name'].';';

			// Antal
			if (in_array("-Alle-",$_POST['colsel']) || in_array("antal",$_POST['colsel']))
			{
				if (substr($d["temporary_uniqid"], 0, 6) == "RETURN")
					$res .= '"1";';
				else if(substr($d["temporary_uniqid"], 0, 6) == "KREDIT" )
					$res .= '"-1";';
				else
					$res .= '"1";';
			}

			// S�lger
			if (in_array("-Alle-",$_POST['colsel']) || in_array("saelger",$_POST['colsel'])) {
				if($d['dealer_user_id']!=0) {
					$dealer_who = $utilDB->secDB->db_get("SELECT user_name FROM `swc_account_users` WHERE user_id='".$d['dealer_user_id']."';");
					$res .= '"'.($dealer_who["user_name"]=='' ? "Standard" : $dealer_who["user_name"]).'";';
				} else {
					$res .= '"Standard";';
				}
			}

			// Annulleringsdato
			if (in_array("-Alle-",$_POST['colsel']) || in_array("annulleringsdato",$_POST['colsel']))
				$res .= '"'.($d['CanceledDate'] == "0000-00-00" ? "-" : $d['CanceledDate']).'";';

			// Annulleringsgrund
			if (in_array("-Alle-",$_POST['colsel']) || in_array("annulleringsgrund",$_POST['colsel']))
				$res .= '"'.mysql_real_escape_string(@$cancelTexts[$d['CanceledText']]).'";';

			// Forsikringstype
			if (in_array("-Alle-",$_POST['colsel']) || in_array("forsikringstype",$_POST['colsel']))
				$res .= '"'.(@$insurance_types[$d['insurance_group_id']] == "" ? "FULLCARE" : @$insurance_types[$d['insurance_group_id']]).'";';

			// Kategori
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kategori",$_POST['colsel']))
				$res .= '"'.@$insurance_group_names[$d['insurance_group_id']].'";';

			// Aktiveringsdato
			if (in_array("-Alle-",$_POST['colsel']) || in_array("aktiveringsdato",$_POST['colsel'])) {
				if($d['policy_number'] == '' && $d['activation_date'] == "0000-00-00")
					$res .= '"dd-mm-yyyy";';
				else
					$res .= '"'.DateFormat($d['activation_date']).'";';
			}

			// D�kningstype til TopDK
			if (in_array("-Alle-",$_POST['colsel']) || in_array("d�kningstype",$_POST['colsel']))
				$res .= '"'.@$insurance_coverage_names[$d['insurance_group_id']].'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("kontant",$_POST['colsel']))
				$res .= '"'.($d['pay_type'] == "pbs" ? "Nej" : "Ja").'";';

			//HUE HUE hue
			if (in_array("-Alle-",$_POST['colsel']) || in_array("betaltibutik",$_POST['colsel']))
				$res .= '"'.($d['paid'] == "" ? toDecimal($d['policy_price']) : toDecimal($d['paid'])).'";';//$d['paid']).'";'; //betaltibutik	//nogle gange returnerer denne et bel�b alla"299" nogle gange "267.00", punktummet forstyrer i CSV formatet.

			if (in_array("-Alle-",$_POST['colsel']) || in_array("saelgerregdato",$_POST['colsel']))
				$res .= '"'.($d['shelf_user_assigned'] == "0000-00-00" ? "dd-mm-yyyy" : DateFormat($d['shelf_user_assigned'])).'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("mail",$_POST['colsel'])) {
				$email = $utilDB->secDB->db_get("SELECT email FROM `swc_account` WHERE account_id='".$d['account_id']."';");
				$res .= '"'.$email['email'].'";';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("gade",$_POST['colsel']))
				$res .= '"'.str_replace("\n", "", $acc1['street_address']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("postnr",$_POST['colsel']))
				$res .= '"'.$acc1['postcode'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("by",$_POST['colsel']))
				$res .= '"'.$acc1['city'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("lel",$_POST['colsel']))
				$res .= '"'.($d['lel'] == 0 ? "Nej" : "Ja").'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("risikoklasse",$_POST['colsel']))
				$res .= '"'.$grp['risk_name'].'";';

			$costDataSQL = "
				SELECT
					pich.*, pid.name as price_level
				FROM
					`securator_dk`.`products_insurance_cost_history` pich
				LEFT JOIN
					`securator_dk`.`products_insurance` pi ON pich.price_id = pi.rec_id
				LEFT JOIN
					`securator_dk`.`products_insurance_data` pid ON pid.rec_id = pi.rec_id
				WHERE
					pich.`group_id` = '".$d["insurance_group_id"]."' AND
					pi.`price` >= '".$d["policy_price"]."' AND
					pich.`createdate` <= '".$d["create_date"]."'
				ORDER BY
					pi.`price` ASC , pich.`createdate` DESC Limit 1;
			";

			$costData = $utilDB->secDB->db_get($costDataSQL);

			if(!$costData)
			{
				$costDataSQL = "
					SELECT
						pi.*, pid.name as price_level
					FROM
						`products_insurance` pi
					LEFT JOIN
						`securator_dk`.`products_insurance_data` pid ON pid.rec_id = pi.rec_id
					WHERE
						pi. `group_id` = '".$d["insurance_group_id"]."' AND
						pi.`price` >= '".$d["policy_price"]."'
						ORDER BY
						pi.`price` ASC Limit 1;
				";
				$costData = $utilDB->secDB->db_get($costDataSQL);
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("frontingfee",$_POST['colsel']))
			{
				$frontingfee = (($d["policy_price"]-$d['dealer_profit'])-$costData['costs_allrisk_premium'])*($costData['costs_frontingfee']/100);
				if($frontingfee > 0)
					$res .= '"'.MoneyFormat($frontingfee).'";';
				else
					$res .= '"'.MoneyFormat(0).'";';
			}
			if (in_array("-Alle-",$_POST['colsel']) || in_array("skadeforsikringsafgift",$_POST['colsel']))
				$res .= '"'.MoneyFormat(($costData['costs_risk_premium']+$costData['costs_allrisk_premium'])*($costData['costs_insurance_damage']/100)).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("salgsomkostning",$_POST['colsel']))
				$res .= '"'.MoneyFormat($d["policy_price"]*($costData['costs_sales_cost']/100)).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("aeskeomkostning",$_POST['colsel']))
				$res .= '"'.MoneyFormat($costData['costs_box_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("saelgerprovision",$_POST['colsel']))
				$res .= '"'.MoneyFormat($d["policy_price"]*($costData['costs_dealer_commission']/100)).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("sikkernet_support",$_POST['colsel']))
				$res .= '"'.MoneyFormat($costData['costs_sikkernet']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("risikopraemie",$_POST['colsel']))
				$res .= '"'.MoneyFormat($costData['costs_risk_premium']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("risikopraemie2",$_POST['colsel']))
				$res .= '"'.MoneyFormat($costData['costs_risk_premium2']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("allriskpraemie",$_POST['colsel']))
				$res .= '"'.MoneyFormat($costData['costs_allrisk_premium']).'";';

			$res .= '"'.$costData['price_level'].'";';

			$res .= "\r\n";

			fwrite($fh, $res);
			$res = null;
		}

		if ($res)
		{
			ob_end_clean();
			header("Cache-Control: must-revalidate, cache, post-check=0, pre-check=0");
			header("Pragma: public");
			header("Content-Length: ".strlen($res));
			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=callcenter.csv");
			echo $res;
			exit;
		}
	}
	fclose($fh);
}

if ($messages->isEmpty() == false)
	$messages->DrawErrorBox(_LANG('system','error'));

stdHeader("Forsikring CSV Eksport - afgr�nsning");

$chainArray = $utilDB->getShopChains();
$pcArray = $utilDB->getProductClasses();
drawMenuSelectors(@$_POST['startDate'], @$_POST['endDate'], @$_POST['shopchain'], $chainArray, @$_POST['statusselected'], $pcArray, @$_POST['productclass'], $csvColumns);

echo PageFunctionButton(_LANG('system', 'back'), "", "./modules/callcenter/callcenter");
?>
