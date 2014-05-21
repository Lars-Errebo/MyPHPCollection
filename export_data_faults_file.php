<?php

defined('POINTADMINURL') or define('POINTADMINURL',"/modules/callcenter/pointsystem_admin");

include_once dirname($_SERVER["SCRIPT_FILENAME"]) . '/securator/securator.util_db.php';

$utilDB = new SECAdminDB;
set_time_limit(3600);

error_reporting(0);

AccountMustBeOnline(ACCESS_CALLCENTER, 'CALLCENTER');

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

function drawMenuSelectors($startdate, $enddate, $chainSelected, $chainArray, $productClasses, $prodclassSelected, $csvColumns)
{
	echo '
		<link rel="stylesheet" type="text/css" href="' . POINTADMINURL . '/datepickr.css" />
      	<form id="form1" name="form1" method="post" action="">
          <table border="0" cellspacing="0" cellpadding="0">';
	echo '<tr><td><div style="width:100px;"><strong>Startdato</strong></div></td><td>';
	echo '<input style="width:90px;" type="text" name="startDate" id="startDate" value="' . $startdate . '" />';

	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">
			         new datepickr(\'startDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'søndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'lørdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';
	echo "</td></tr>";

	echo '<tr><td><div style="width:100px;"><strong>Slutdato</strong></div></td>';
	echo '<td>';
	echo '<input style="width:90px;" type="text" name="endDate" id="endDate" value="' . $enddate . '" />';
	echo '<script type="text/javascript" src="' . POINTADMINURL . '/datepickr.js"></script>
		  <script type="text/javascript">
			         new datepickr(\'endDate\', {
				\'dateFormat\': \'d-m-Y\',
					weekdays: [\'søndag\', \'mandag\', \'tirsdag\', \'onsdag\', \'torsdag\', \'fredag\', \'lørdag\'],
				    months: [\'januar\', \'februar\', \'marts\', \'april\', \'maj\', \'juni\', \'juli\', \'august\', \'september\', \'oktober\', \'november\', \'december\'],
	});</script>';
	echo '</td></tr>';

	echo '<tr><td><div style="width:100px;"><strong>Kæde</strong></div></td>';
	echo '<td><select name="shopchain" style="width:150px;">
		<option value="">Vælg kæde</option>';
	foreach ($chainArray as $curChain)
	{
		echo '<option value="' . $curChain['rec_id'] . '"';
		if (!empty($chainSelected) && $chainSelected == $curChain['rec_id'])
			echo 'selected="selected"';
		echo '>' . $curChain['name'] . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><td><div style="width:100px;"><strong>Klasse</strong></div></td>';
	echo '<td><select name="productclass" style="width:150px;">
		<option value="">Vælg klasse</option>';
	foreach ($productClasses as $curPC)
	{
		echo '<option value="' . $curPC['name'] . '"';
		if (!empty($prodclassSelected) && $prodclassSelected == $curPC['name'])
			echo 'selected="selected"';
		echo '>' . $curPC['name'] . '</option>';
	}
	echo '</select></td>';
	echo '</tr>';

	echo '<tr><td><div style="width:100px;"><strong>Udvælg kolonner til export</strong></div></td>';
	echo '<td><select name="colsel[]" multiple style="width:150px;">';
	echo '<option value="-Alle-" selected>-Alle-</option>';
	$cols = explode(";", $csvColumns);
	foreach ($cols as $col)
	{
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

$header  = "skadenr;kunde;policenummer;praemie;salgsdato;forhandler;klasse;producent;model;produktkoebspris;skadetype;";
$header .= "skadedato;vaerksted;reperationspris;koerselpris;timepris;reservedelepris;opgaveantal;kulance;skadesindtaegt;";
$header .= "sagsnavn;erhverv;ombytning;fejldisponering;status;lukket;statusdato;postnr;gruppenavn;kaede;forsikringstype;";
$header .= "selvrisiko;allrisk;opgavetype;sidstopdateret;forsikringsselskab;indtaegtegosalg;serienummer;adresse;";
$header .= "postnrby;fastnet;mobilnr;kontant;email;dækningstype;policestatus;lobetid;kreditnota;risikoklasse";

if (isset($_POST['startDate']))
{
	if (isset($_POST['startDate']))
	{
		$myDateTime = DateTime::createFromFormat('d-m-Y', $_POST['startDate']);
		$ymdStartDate = $myDateTime->format('Y-m-d');

		if (!empty($ymdStartDate))
			$start_date = "AND created >= '".$ymdStartDate." 00:00:00' ";
	}

	if (isset($_POST['endDate']))
	{
		$myDateTime = DateTime::createFromFormat('d-m-Y', $_POST['endDate']);
		$ymdEndDate = $myDateTime->format('Y-m-d');

		if (!empty($ymdStartDate))
			$end_date = "AND created <= '".$ymdEndDate." 23:59:59' ";
	}
	if (($start_date == "") || ($end_date == ""))
	{
		$messages->Error('callcenter', 'export_dates_must_be_set');
	}
	else
	{
		if (!empty($_POST['shopchain']))
		{
			$dealerIds = $utilDB->getDealerIdsFromChain($_POST['shopchain']);
			$datasql = "
				SELECT
					*
				FROM
					cc_tasks ct, products_customers pc
				WHERE
					ct.account_id != -1 AND
					pc.rec_id=ct.customer_product_id AND
					pc.dealer_id IN (" . $dealerIds . ") $start_date $end_date
				ORDER BY
					ct.task_id
			";
		}
		else
		{
			$datasql = "
				SELECT
					*
				FROM
					cc_tasks
				WHERE
					account_id != -1
					$start_date $end_date
				ORDER BY
					task_id
			";
		}

		// Finder forsikringstype (FULLCARE, ALLCARE, m.m.)
		$insurance_types_fetch = $utilDB->secDB->db_fetch_array("
			SELECT
				`pig`.`group_id`, `pic`.`name`, `pic`.`name2`
			FROM
				products_insurance_group `pig`
			LEFT JOIN
				products_insurance_coverage `pic` ON `pic`.`id`=`pig`.`coverage_id`
		");
		foreach ($insurance_types_fetch as $itf)
		{
			$insurance_types[$itf["group_id"]] = $itf["name"];
			$coverage_types[$itf["group_id"]] = $itf["name2"];
		}

		$insurance_task_types_fetch = $utilDB->secDB->db_fetch_array("SELECT `task_type_id`,`name` FROM `cc_task_types_data`");
		foreach ($insurance_task_types_fetch as $ittf)
		{
			if($ittf["name"] != "sdf")
			$insurance_task_types[$ittf["task_type_id"]] = $ittf["name"];
		}

		$res = "";
		foreach (explode(";", $header) as $curCol)
		{
			if ((in_array($curCol,$_POST['colsel']) && $curCol != "-Alle-") || in_array("-Alle-",$_POST['colsel']))
				$res .= $curCol . ";";
		}
		$res .= "\n";

		$myFile = "/opt/export/export_fault.csv";
		$fh = fopen($myFile, 'w+') or die("can't open file");
		fwrite($fh, $res);
		$res = null;


		$result = mysql_query($datasql);
		while( ($d = mysql_fetch_assoc($result)) != FALSE)
		{
			$close_info = $utilDB->secDB->db_get("SELECT * FROM cc_tasks_closed_data WHERE task_id='".$d['task_id']."'");
			$info = $utilDB->secDB->db_get("
				SELECT
					pip.*, period_diff(pip.end_date,pip.start_date) as per, aab.*, sa.email
				FROM
					products_insurance_policies pip, swc_account_address_book aab
				LEFT JOIN
					swc_account sa ON sa.account_id=aab.account_id
				WHERE
					pip.product_customers_id='".$d['customer_product_id']."' AND
					aab.account_id=pip.account_id;
			");
			$info["chain"] = $utilDB->secDB->db_get("
				SELECT
					swc_account_groups.name
				FROM
					`swc_account`
				LEFT JOIN
					`swc_account_groups` ON `swc_account`.`groups` = CONCAT(',' , swc_account_groups.rec_id, ',')
				WHERE
					`swc_account`.`account_id` = '".$info["dealer_id"]."'
			");
			$grp = $utilDB->secDB->db_get("
				SELECT
					pg.name AS group_name, pc.name AS class_name
				FROM
					products_group pg, products_class pc
				WHERE
					pg.group_id='".$info['product_group_id']."' AND
					pc.class_id = pg.class_id
			");
			if (!empty($_POST['productclass']) && $_POST['productclass'] != $grp['class_name']) {
				unset($group);
				continue;
			}
			$infosales = $utilDB->secDB->db_get("SELECT company FROM `swc_account_address_book` WHERE account_id='".$info["dealer_id"]."' ");
			$entry = $utilDB->secDB->db_get("SELECT created FROM cc_tasks_entry WHERE task_id='".$d['task_id']."' ORDER BY rec_id DESC ");
			$status = $utilDB->secDB->db_get("SELECT cs.rec_id,csd.name FROM cc_status cs, cc_status_data csd
												WHERE csd.rec_id=cs.rec_id AND cs.status_id='".$d['status']."' AND language_id='".SYSTEM_LANGUAGE."'");

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
					pich.`group_id` = '".$info["insurance_group_id"]."' AND
					pi.`price` >= '".$info["policy_price"]."' AND
					pich.`createdate` <= '".$info["create_date"]."'
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
						pi. `group_id` = '".$info["insurance_group_id"]."' AND
						pi.`price` >= '".$info["policy_price"]."'
						ORDER BY
						pi.`price` ASC Limit 1;
				";
				$costData = $utilDB->secDB->db_get($costDataSQL);
			}

			if ($info['product_group_id'])
				$group = $utilDB->secDB->db_get("
					SELECT pg.name,prc.risk_name FROM products_group pg LEFT JOIN products_class pc ON pc.class_id = pg.class_id
					LEFT JOIN products_risk_class prc ON prc.risk_id = pg.risk_id
					WHERE pg.group_id = '".$info['product_group_id']."'
				");
			else
				$group['name'] = "";

			if ($d['customer_product_id'])
				$multi_rep = $utilDB->secDB->db_get("SELECT count(*) as count FROM cc_tasks WHERE customer_product_id='".$d['customer_product_id']."' ");
			else
				$multi_rep = 0;

			$damage = "";
			if ($close_info)
				$damage = $utilDB->secDB->db_get("SELECT name FROM cc_fault_data WHERE fault_id='".$close_info['fault_id']."' AND language_id='".SYSTEM_LANGUAGE."'");

			$repair = $utilDB->secDB->db_get("SELECT repair_id, sc.name FROM cc_tasks_entry te, service_company sc
					WHERE te.task_id='".$d['task_id']."' AND sc.rec_id=te.repair_id ORDER BY te.rec_id DESC ");
			if ($d['case_no'] == '')
				$d['case_no'] = "SEC.".$d['task_id'];
			if (strpos($d['case_no'], ".") === 0)
				$d['case_no'] = "SEC".$d['case_no'];

			if (in_array("-Alle-",$_POST['colsel']) || in_array("skadenr",$_POST['colsel']))
				$res .= '"'.$d['task_id'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kunde",$_POST['colsel']))
				$res .= '"'.@$info['name'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("policenummer",$_POST['colsel']))
				$res .= '"'.$info['policy_number'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("praemie",$_POST['colsel']))
				$res .= '"'.MoneyFormat($info['policy_price']).'";';

			$prod = $utilDB->secDB->db_get("SELECT pc.purchase_date, pc.product_model, pc.purchase_price, pm.name FROM products_customers pc, products_manufactures pm
											WHERE pc.rec_id='".$d['customer_product_id']."' AND pm.rec_id=pc.manufacturer_id ");

			if (in_array("-Alle-",$_POST['colsel']) || in_array("salgsdato",$_POST['colsel']))
				$res .= '"'.DateFormat($prod['purchase_date']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("forhandler",$_POST['colsel']))
				$res .= '"'.$infosales['company'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("klasse",$_POST['colsel']))
				$res .= '"'.@$group['name'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("producent",$_POST['colsel']))
				$res .= '"'.$prod['name'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("model",$_POST['colsel']))
				$res .= '"'.str_replace('"', '', $prod['product_model']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("produktkoebspris",$_POST['colsel']))
				$res .= '"'.MoneyFormat($prod['purchase_price']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("skadetype",$_POST['colsel']))
				$res .= '"'.@$damage['name'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("skadedato",$_POST['colsel']))
				$res .= '"'.DateFormat($d['created']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("vaerksted",$_POST['colsel']))
				$res .= '"'.@$repair['name'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("reperationspris",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['total_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("koerselpris",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['transport_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("timepris",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['hour_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("reservedelepris",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['spareparts_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("opgaveantal",$_POST['colsel']))
				$res .= '"'.$multi_rep['count'].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kulance",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['extra_cost']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("skadeindtaegt",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['extra_income']).'";';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("sagsnavn",$_POST['colsel'])) {
				if ($info['case_id'] > 0)
				{
					$case = $utilDB->secDB->db_get("SELECT name FROM products_insurance_case WHERE rec_id='".$info['case_id']."' ");
					$res .= $case['name'].';';
				}
				else
					$res .= ';';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("erhverv",$_POST['colsel'])) {
				if ($info['business'] == 0)
					$res .= 'Nej;';
				else
					$res .= 'Ja;';
			}

			if (in_array("-Alle-",$_POST['colsel']) || in_array("ombytning",$_POST['colsel']))
				$res .= MoneyFormat(@$close_info['replace_cost']).';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("fejldisponering",$_POST['colsel']))
				$res .= MoneyFormat(@$close_info['fault_amount']).';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("status",$_POST['colsel']))
				$res .= $status['name'].';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("lukket",$_POST['colsel']))
				$res .= ($d['closed']==0?'Nej':'Ja').';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("statusdato",$_POST['colsel']))
				$res .= DateFormat($entry['created']).';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("postnr",$_POST['colsel']))
				$res .= @$info['postcode'].';';

			if (in_array("-Alle-",$_POST['colsel']) || in_array("gruppenavn",$_POST['colsel'])) {
				if ($grp)
				{
					$res .= $grp['group_name'].';';
				}
				else
				{
					$res .= ';';
				}
			}
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kaede",$_POST['colsel']))
				$res .= $info['chain']['name'].';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("forsikringstype",$_POST['colsel']))
				$res .= (@$insurance_types[$info['insurance_group_id']] == "" ? "FULLCARE" : @$insurance_types[$info['insurance_group_id']]).';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("selvrisiko",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['excesses']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("allrisk",$_POST['colsel']))
				$res .= ($close_info["risktype"] == 1 ? "Ja" : "Nej").';';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("opgavetype",$_POST['colsel']))
				$res .= '"'.@$insurance_task_types[$d["task_type"]].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("sidstopdateret",$_POST['colsel']))
				$res .= '"'.($close_info["lastupdate"] == "" ? DateFormat("0000-00-00 00:00:00") : DateFormat($close_info["lastupdate"]) ).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("forsikringsselskab",$_POST['colsel'])) {
				if((int)$info["home_insurance_company"] == 0) {
				    $res .= '"";';
				} else {
					$home_insurance = $utilDB->secDB->db_get("SELECT name FROM `swc_insurance_companies` WHERE `rec_id` = '".(int)$info["home_insurance_company"]."'");
					$res .= '"'.@$home_insurance["name"].'";';
				}
			}
			if (in_array("-Alle-",$_POST['colsel']) || in_array("indtaegtegosalg",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['income_double_insurance']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("serienummer",$_POST['colsel']))
				$res .= '"'.@$info["serial_no"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("adresse",$_POST['colsel']))
				$res .= '"'.@$info["street_address"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("postnrby",$_POST['colsel']))
				$res .= '"'.@$info["postcode"].' '.@$info["city"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("fastnet",$_POST['colsel']))
				$res .= '"'.@$info["phone_home"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("mobilnr",$_POST['colsel']))
				$res .= '"'.@$info["mobile_phone"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kontant",$_POST['colsel']))
				$res .= '"'.( is_null(@$info["pay_type"]) || @$info["pay_type"] == "cash" ? "Ja" : "Nej" ).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("email",$_POST['colsel']))
				$res .= '"'.@$info["email"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("dï¿½kningstype",$_POST['colsel']))
				$res .= '"'.@$coverage_types[$info['insurance_group_id']].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("policestatus",$_POST['colsel']))
				$res .= '"'.@$info["status"].'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("lobetid",$_POST['colsel']))
				$res .= '"'.(@$info["per"]/100).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("kreditnota",$_POST['colsel']))
				$res .= '"'.MoneyFormat(@$close_info['creditnote']).'";';
			if (in_array("-Alle-",$_POST['colsel']) || in_array("risikoklasse",$_POST['colsel']))
				$res .= '"'.$group["risk_name"].'";';


			$res .= '"'.$costData['price_level'].'";';

			$res .= "\r\n";

			fwrite($fh, $res);
			$res = null;

			unset($d);
			unset($group);
		}
		$res = str_replace("dd-mm-yyyy", "01-05-2008", $res);
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
	fclose($fh);
}

if ($messages->isEmpty() == false)
	$messages->DrawErrorBox(_LANG('system','error'));

stdHeader("Skade CSV Eksport - afgrænsning");

$chainArray = $utilDB->getShopChains();
$pcArray = $utilDB->getProductClasses();

drawMenuSelectors(@$_POST['startDate'], @$_POST['endDate'], @$_POST['shopchain'], $chainArray, $pcArray, @$_POST['productclass'], $header);

echo PageFunctionButton(_LANG('system', 'back'), "", "./modules/callcenter/callcenter");
?>
