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
				$acc = db_get(	"SELECT 1 ".
								"from pool_storage_list ".
								"where shortnames = '".$parts[0]."' and ".
								"      colli_id = '".$parts[1]."' and".
								"      label_id = '".$parts[2]."'");
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

function drawMenuSelectors($startdate, $enddate, $chainSelected, $chainArray, $statusSelected, $productClasses, $prodclassSelected)
{
	echo '
		<link rel="stylesheet" type="text/css" href="' . POINTADMINURL . '/datepickr.css" />
      	<form id="form1" name="form1" method="post" action="">
          <table border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td><div style="width:40px;"><strong>Startdato</strong></div></td>
              <td><div style="width:40px;"><strong>Slutdato</strong></div></td>
			  <td><div style="width:70px;"><strong>K�de</strong></div></td>
			  <td><div style="width:70px;"><strong>Klasse</strong></div></td>
			  <td><div style="width:40px;"><strong>Udtr�k efter</strong></div></td>
			  </tr>
	';
	echo '<tr><td>';
	echo '<input style="width:90px;" type="text" name="startDate" id="startDate" value="' . $startdate . '" />';

	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">			
			         new datepickr(\'startDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'s�ndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'l�rdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';	
	echo "</td>";

	echo '<td>';
	echo '<input style="width:90px;" type="text" name="endDate" id="endDate" value="' . $enddate . '" />';
	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">			
			         new datepickr(\'endDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'s�ndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'l�rdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';	
	echo '</td>';

	echo '<td><select name="shopchain">
		<option value="">V�lg k�de</option>';
	foreach ($chainArray as $curChain) {
		echo '<option value="' . $curChain['rec_id'] . '"';
		if (!empty($chainSelected) && $chainSelected == $curChain['rec_id'])
			echo 'selected="selected"';
		echo '>' . $curChain['name'] . '</option>';
	}	
	echo '</select></td>';
	
	echo '<td><select name="productclass">
		<option value="">V�lg klasse</option>';
	foreach ($productClasses as $curPC) {
		echo '<option value="' . $curPC['name'] . '"';
		if (!empty($prodclassSelected) && $prodclassSelected == $curPC['name'])
			echo 'selected="selected"';
		echo '>' . $curPC['name'] . '</option>';
	}	
	echo '</select></td>';
	
	echo '<td><select name="sortby">';
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
	echo '</select></td>';
	echo '</tr>';
	echo '
        	<tr>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
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

if (isset($_POST['sortby']))
{
	if(@$_POST["sortby"] == "2")
	{
		$sortbyfield = "`activation_date`";
	}
	else
	{
		$sortbyfield = "`create_date`";
	}
	if (isset($_POST['startDate']))
	{
		$myDateTime = DateTime::createFromFormat('d-m-Y', $_POST['startDate']);
		$ymdStartDate = $myDateTime->format('Y-m-d');
		
		if (!empty($ymdStartDate))
			$start_date = "AND ".$sortbyfield." >= '".$ymdStartDate."' ";
	}
	
	if (isset($_POST['endDate']))
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
					swc_account sa ON sa.account_id=pip.dealer_id and sa.groups = concat(',', ".@$_POST["group_id"].", ',')
				JOIN 
					swc_account_groups sag ON sag.rec_id = ".@$_POST["group_id"];
		}
		else
		{
			$group_joins = "
				left JOIN 
					swc_account sa ON sa.account_id=pip.dealer_id
				left JOIN 
					swc_account_groups sag ON sa.groups = concat(',', sag.rec_id, ',') 
			";
		}
		if (!empty($_POST['shopchain'])) {
			$dealerIds = $utilDB->getDealerIdsFromChain($_POST['shopchain']);
			$whereDealerIds = "AND pip.dealer_id IN (" . $dealerIds . ") ";
		} else {
			$whereDealerIds = "";
		}
		
			
		
		$sql = "
			SELECT pip.dealer_id,pip.account_id,pip.product_group_id,pip.start_date,pip.policy_number,pip.rec_id,pip.manufacturer_id,pip.product_model,
				pip.serial_no,pip.purchase_price,pip.policy_price,pip.status,pip.case_id,pip.business,pip.create_date,pip.temporary_uniqid,pip.dealer_profit,
				pip.CanceledDate,pip.CanceledText,pip.insurance_group_id,pip.dealer_user_id,pip.activation_date,pip.pay_type,
				pip.shelf_user_assigned,pip.lel,pip.online_purchase,pip.pool_id,				
				period_diff(end_date,start_date) as per, 
				insurance_pool, 
				sag.name as 'swc_account_groups_name',
				`swc_account_users`.`user_name` AS usr,
				`Payment_Services`.paid
			FROM 
				products_insurance_policies pip
			LEFT JOIN
				`swc_account_users` ON `swc_account_users`.`user_id`=pip.dealer_user_id
			LEFT JOIN
				`Payment_Services` ON `Payment_Services`.`insurance`=pip.rec_id
			LEFT JOIN  
				products_insurance_group on insurance_group_id = group_id
			$group_joins 
			WHERE 
				/*status!='Canceled' AND*/ 
				(online_purchase='No' OR online_purchase='Paid') $whereDealerIds $start_date $end_date ORDER BY rec_id

		";
		
		// Pr�-indl�sning af annulleringstekster..
		$cancelQuery = $utilDB->secDB->db_fetch_array(" SELECT id, text FROM `cancel_texts` ");
		$cancelTexts = array(0 => "-");
		if ($cancelQuery) {
			foreach($cancelQuery as $cq) {
				$cancelTexts[$cq['id']] = $cq['text'];
			}
		}
	
		// Finder forsikringstype (FULLCARE, ALLCARE, m.m.)
			
		$insurance_types_fetch = $utilDB->secDB->db_fetch_array("
			SELECT `pig`.`group_id`, `pic`.`name` FROM products_insurance_group `pig` 
			LEFT JOIN products_insurance_coverage `pic` ON `pic`.`id`=`pig`.`coverage_id`;
		");
		foreach ($insurance_types_fetch as $itf) {
			$insurance_types[$itf["group_id"]] = $itf["name"];
		}
				
		$insurance_group_fetch = $utilDB->secDB->db_fetch_array("
			SELECT pg.`group_id`, pg.`name`, pc.name2 FROM `products_insurance_group` pg
			LEFT JOIN products_insurance_coverage pc ON pg.coverage_id=pc.id;
		");
		foreach ($insurance_group_fetch as $igf) {
			$insurance_group_names[$igf["group_id"]] = $igf["name"];
			$insurance_coverage_names[$igf["group_id"]] = $igf["name2"];
		}
					
		$sqlres = $utilDB->secDB->db_fetch_array($sql);
		$res = "";
		$res .= "startdato;alder;policenummer;rec_id;kontoid;navn;adresse;model;serieno;koebspris;gruppe;praemie;forhandler;loebetid;forsikringsstatus;";
		$res .= "gruppenavn;klasse;producent;sagsnavn;erhvervskunde;oprettelsesdato;saelgeravance;salgstype;kaede;antal;saelger;";
		$res .= "annulleringsdato;annulleringsgrund;forsikringstype;kategori;aktiveringsdato;d�kningstype;kontant;betaltibutik;";
		$res .= "saelgerregdato;mail;adresse;postnr;by;lel;risikoklasse;";
		$res .= "frontingfee;skadeforsikringsafgift;salgsomkostning;aeskeomkostning;";
		$res .= "saelgerprovision;sikkernet_support;risikopraemie;risikopraemie2;allriskpraemie;";
		$res .= "\n";
			
			
		foreach ($sqlres as $d)
		{		
			$acc = $utilDB->secDB->db_get("SELECT * FROM swc_account_address_book WHERE account_id='".$d['account_id']."' AND default_addr=1");		
			$grp = $utilDB->secDB->db_get("SELECT prc.risk_name, pg.name AS group_name, pc.name AS class_name 
											FROM products_group pg 
											LEFT JOIN products_class pc ON pc.class_id = pg.class_id 
											LEFT JOIN products_risk_class prc ON prc.risk_id = pg.risk_id 
											WHERE pg.group_id = '".$d['product_group_id']."';");
			if (!empty($_POST['productclass']) && $_POST['productclass'] != $grp['class_name']) {				
				continue;
			}
			$res .= '"'.DateFormat($d['start_date']).'";';
			
			$diff = date_diff(getdate(),$d['start_date']);
			$res .= '"'.( $diff >=0 and $diff <= 365 ? '0' :
			( $diff >=365  and $diff <=  730 ? '1' :
			( $diff >=730  and $diff <= 1095 ? '2' :
			( $diff >=1095 and $diff <= 1460 ? '3' :
			( $diff >=1460 and $diff <= 1825 ? '4' :
			( $diff >=1825 and $diff <= 2190 ? '5' :
			 '6')))))).'";'; 
		    			
			$res .= '"'.$d['policy_number'].'";';
			$res .= '"'.$d['rec_id'].'";';
			
			$acc1 = $acc;
 
			$res .= '"'.$d['account_id'].'";';
			$res .= '"'.$acc['name'];
			if ($acc['name'] != '' && $acc['company'] != '')
				$res .= ' / ';
				
			$res .= $acc['company'].'";';
			
			$res .= '"'. str_replace("\n", ",", $acc['street_address'].' '.$acc['postcode'].' '.$acc['city']) .'";';
				
			$man = $utilDB->secDB->db_get("SELECT * FROM products_manufactures  WHERE rec_id=".$d['manufacturer_id']." ");
			$res .= '"'.str_replace('"', "'",$d['product_model']).'";';
			
			$res .= '"'.$d['serial_no'].'";';
			$res .= '"'.MoneyFormat($d['purchase_price']).'";';
			$purch = $d['purchase_price'];
			$res .= '"'.( $purch >=0 and $diff <= 3499 ? '0-3499' :
			( $diff >=3500 and $diff <=  7499 ? '3500-7499' :
			( $diff >=7500 ? '7500-20000' :
			 '0-3499'))).'";'; 		
			$res .= '"'.MoneyFormat($d['policy_price']).'";';
			
			$acc = $utilDB->secDB->db_get("SELECT * FROM swc_account_address_book WHERE account_id=".$d['dealer_id']." AND default_addr=1");
			$res .= '"'.$acc['name'].'";';
			$res .= '"'.($d['per']/100).'";';
			$res .= '"'.$d['status'].'";';
			if ($grp) {
				$res .= '"'.$grp['group_name'].'";';
				$res .= '"'.$grp['class_name'].'";';
			} else {
				$res .= '"";"";';
			}
			$res .= '"'.$man['name'].'";';
			
			if ($d['case_id'] > 0) {
				$case = $utilDB->secDB->db_get("SELECT name FROM products_insurance_case WHERE rec_id='".$d['case_id']."' ");
				$res .= '"'.$case['name'].'";';
			} else $res .= '"";';
			
			if ($d['business'] == 0)
				$res .= '"Nej";';
			else
				$res .= '"Ja";';
				
			// Oprettelsesdato
			$res .= '"'.DateFormat($d['create_date']).'";';
			
			// S�lgeravance
			if(substr($d["temporary_uniqid"], 0, 6) == "KREDIT" )
			{
				if(MoneyFormat($d['dealer_profit']) < 0)
					$res .= '"'.MoneyFormat($d['dealer_profit']).'";';
				else
					$res .= '"-'.MoneyFormat($d['dealer_profit']).'";';					
			}
			else if (substr($d["temporary_uniqid"], 0, 6) == "RETURN")
			{
				if(MoneyFormat($d['dealer_profit']) < 0)
					$res .= '"'.substr(MoneyFormat($d['dealer_profit']), 1).'";';
				else
					$res .= '"'.MoneyFormat($d['dealer_profit']).'";'; 
			} else {
				$res .= '"'.MoneyFormat($d['dealer_profit']).'";';
			}
			
			// Salgstype
			$res .= calculateSaleType($d);
			
			// K�de
			$res .= $d['swc_account_groups_name'].';';
			
			
			// Antal
			if (substr($d["temporary_uniqid"], 0, 6) == "RETURN")
				$res .= '"1";';
			else if(substr($d["temporary_uniqid"], 0, 6) == "KREDIT" )
				$res .= '"-1";';
			else 
				$res .= '"1";';

			// S�lger
			if($d['dealer_user_id']!=0)
			{			
				$dealer_who = $utilDB->secDB->db_get("SELECT user_name FROM `swc_account_users` WHERE user_id='".$d['dealer_user_id']."';");
				$res .= '"'.($dealer_who["user_name"]=='' ? "Standard" : $dealer_who["user_name"]).'";';
			} else
				$res .= '"Standard";';
			
			// Annuleringsdato
			$res .= '"'.($d['CanceledDate'] == "0000-00-00" ? "-" : $d['CanceledDate']).'";';
			
			// Annuleringsgrund
			$res .= '"'.mysql_real_escape_string(@$cancelTexts[$d['CanceledText']]).'";';
			
			// Forsikringstype
			$res .= (@$insurance_types[$d['insurance_group_id']] == "" ? "FULLCARE" : @$insurance_types[$d['insurance_group_id']]).';'; 

			// Kategori
			$res .= '"'.@$insurance_group_names[$d['insurance_group_id']].'";';
			
            // Aktiveringsdato
			if($d['policy_number'] == '' && $d['activation_date'] == "0000-00-00")
			{
				$res .= '"dd-mm-yyyy";';
			}
			else
			{
				$res .= '"'.DateFormat($d['activation_date']).'";';
			}
					
			// D�kningstype til TopDK
			$res .= '"'.@$insurance_coverage_names[$d['insurance_group_id']].'";';
			
			$res .= '"'.($d['pay_type'] == "pbs" ? "Nej" : "Ja").'";';
						
			//HUE HUE hue
			$res .= '"'.($d['paid'] == "" ? toDecimal($d['policy_price']) : toDecimal($d['paid'])).'";';//$d['paid']).'";'; //betaltibutik	//nogle gange returnerer denne et bel�b alla"299" nogle gange "267.00", punktummet forstyrer i CSV formatet.	
			
			
			$res .= '"'.($d['shelf_user_assigned'] == "0000-00-00" ? "dd-mm-yyyy" : DateFormat($d['shelf_user_assigned'])).'";';
			
			$email = $utilDB->secDB->db_get("SELECT email FROM `swc_account` WHERE account_id='".$d['account_id']."';");
			$res .= '"'.$email['email'].'";';
			
			$res .= '"'.str_replace("\n", "", $acc1['street_address']).'";';
			$res .= '"'.$acc1['postcode'].'";';
			$res .= '"'.$acc1['city'].'";';
			$res .= '"'.($d['lel'] == 0 ? "Nej" : "Ja").'";';
			$res .= '"'.$grp['risk_name'].'";';

			$pricelevel = $utilDB->secDB->db_get("
				SELECT * FROM
					`products_insurance`
				WHERE
					`group_id` = '".$d["insurance_group_id"]."' AND
					`price` = '".$d["policy_price"]."';
			");

			$costDataSQL = "
				SELECT * FROM `products_insurance_cost_history`
				WHERE
					`group_id` = '".$d["insurance_group_id"]."' AND
					`price_id` = '".$pricelevel["rec_id"]."' AND
					`createdate` <= '".$d["create_date"]."'
				ORDER BY
					`createdate`  ASC Limit 1;
			";
			
			$costData = $utilDB->secDB->db_get($costDataSQL);
			
			if(!$costData)
			{
				$costDataSQL = "
					SELECT * FROM
						`products_insurance_cost_history` pich
					LEFT JOIN
						`products_insurance` pi ON pi.group_id = pich.group_id
					WHERE
						pich.`group_id` = '".$d["insurance_group_id"]."' AND
						pi.`price` >= '".$d["policy_price"]."' AND
						pich.`createdate` <= '".$d["create_date"]."'
					ORDER BY
						pi.`price` ASC , pich.`createdate` DESC Limit 1;
					";			
				$costData = $utilDB->secDB->db_get($costDataSQL);
			}
			
			
			
			$res .= '"'.MoneyFormat((($d["policy_price"]-$d['dealer_profit'])-$costData['costs_allrisk_premium'])*($costData['costs_frontingfee']/100)).'";';
			$res .= '"'.MoneyFormat(($costData['costs_risk_premium']+$costData['costs_allrisk_premium'])*($costData['costs_insurance_damage']/100)).'";';
			$res .= '"'.MoneyFormat($d["policy_price"]*($costData['costs_sales_cost']/100)).'";';
			$res .= '"'.MoneyFormat($costData['costs_box_cost']).'";';
			
			$res .= '"'.MoneyFormat($d["policy_price"]*($costData['costs_dealer_commission']/100)).'";';
			$res .= '"'.MoneyFormat($costData['costs_sikkernet']).'";';
			$res .= '"'.MoneyFormat($costData['costs_risk_premium']).'";';
			$res .= '"'.MoneyFormat($costData['costs_risk_premium2']).'";';
			$res .= '"'.MoneyFormat($costData['costs_allrisk_premium']).'";';
			
			
			$res .= "\r\n";
		}
		if ($res)
		{
			ob_end_clean();
			header("Cache-Control: must-revalidate, cache, post-check=0, pre-check=0");
			header("Pragma: public");
			header("Content-Length: ".strlen($res));
			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=callcenter.csv");
			print $res;
			exit;
		}
	}
}

if ($messages->isEmpty() == false) 
	$messages->DrawErrorBox(_LANG('system','error'));

stdHeader("Forsikring CSV Eksport - afgr�nsning");

$chainArray = $utilDB->getShopChains();
$pcArray = $utilDB->getProductClasses();
drawMenuSelectors(@$_POST['startDate'], @$_POST['endDate'], @$_POST['shopchain'], $chainArray, @$_POST['statusselected'], $pcArray, @$_POST['productclass']);

echo PageFunctionButton(_LANG('system', 'back'), "", "./modules/callcenter/callcenter");
?>
