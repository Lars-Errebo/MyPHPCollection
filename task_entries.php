<?php

AccountMustBeOnline(ACCESS_CALLCENTER);

require_once('form.php');
require_once('class_callcenter.php');
require_once("fileupload.php");

require_once("securator/securator.telecare.class.php");

$doedit = 0;
$docreate = 0;

$task_id =(int)@$_GET['task_id'];
$rec_id=(int)@$_GET['rec_id'];


if($task_id == 0)
{
	@$task_id = (int)$_POST['task_id'];
}

$cc = new CallCenter();
$lock_id = $cc->LockTask($task_id);

if ($lock_id != 0)
	RegisterUnloadPageCall("/modules/callcenter/unlock_task.php?lock_id=$lock_id");

$cc_entry = NULL;
$sendTo = db_get("
	SELECT
		sa.email, REPLACE(saab.mobile_phone, ' ', '') AS mob
	FROM
		`cc_tasks` cct
	LEFT JOIN
		`swc_account` sa ON cct.customer_id = sa.account_id
	LEFT JOIN
		`swc_account_address_book` saab ON cct.customer_id = saab.account_id
	WHERE
		cct.`task_id` = '".$task_id."';
");

function htmlremover($data)
{
	$html = $data;
	$html = str_replace(">", "> ", $html);
	$html = strip_tags($html);
	$html = str_replace( array("     ", "    ", "   ", "   ", "  ") , " ", $html);
	$html = str_replace( array("\t", "\r"), " ", $html);
	$html = str_replace( array("\n   ", "\n  ", "\n ", "\n ", "\n "), "\n", $html);

	$html = str_replace(array("&AElig;", "&Oslash;", "&Aring;"), array("Æ", "Ø", "Å"), $html);
	$html = str_replace(array("&aelig;", "&oslash;", "&aring;"), array("æ", "ø", "å"), $html);

	$html = preg_replace("/&#?[a-z0-9]+;/i","", $html);
	return $html;
}

function updateResponseTime($task_id, $days=7)
{
	$update_id = mysql_query("
		UPDATE `cc_tasks` SET
			`last_customer_contact` = NOW(),
			`next_response` = DATE_ADD(NOW(), INTERVAL ".$days." DAY)
		WHERE
			`cc_tasks`.`task_id` ='".$task_id."' LIMIT 1;
	");
}

function offerEntry($task_id, $status, $visibility = 50)
{
	global $account;

	$sql = "INSERT INTO `cc_tasks_entry` (
			`task_id`, `rec_id`, `account_id`, `responsible_id`,
			`created`, `subject`, `note`, `status`,
			`note_type_id`, `entry_visibility`
		) VALUES (
			'".(int)$task_id."', null, '".$account->id."', 0, NOW(),
			'".mysql_real_escape_string($status)."',
			'<p>".mysql_real_escape_string($status)."</p>',
			'60',1,".$visibility.
		")";
	$query_entry = mysql_query($sql) or die(mysql_error());
	mysql_query("UPDATE `cc_tasks` SET `status` = '60' WHERE `task_id` = '".(int)$task_id."';");
	updateResponseTime($task_id, 7);
	return true;
}

function fetchUrl($url)
{
	$options = array( 'http' => array(
			'user_agent'    => 'securator->fetchUrl',    // who am i
			'max_redirects' => 10,          // stop after 10 redirects
			'timeout'       => 10,         // timeout on response
	) );
	$context = stream_context_create( $options );
	$page    = @file_get_contents( $url, false, $context );

	$result  = array( );
	if ( $page != false )
		$result['content'] = $page;
	else if ( !isset( $http_response_header ) )
		return null;    // Bad url, timeout

	// Save the header
	$result['header'] = $http_response_header;

	// Get the *last* HTTP status code
	$nLines = count( $http_response_header );
	for ( $i = $nLines-1; $i >= 0; $i-- )
	{
		$line = $http_response_header[$i];
		if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
		{
			$response = explode( ' ', $line );
			$result['http_code'] = $response[1];
			break;
		}
	}
	$result['url'] = $url;
	return $result;
}


if(@$_POST["offeranswer"])
{
	if(@$_POST["serviceid"] == 135715)
	{
		$offeranswer = (int)$_POST["offeranswer"];
		$sqlStatus = 0;
		$response = false;
		$tc = new Telecare($task_id);
		if(@$_POST["accept"])
		{
			$response = "qrAccept";
			$sqlStatus = 1;
		}
		elseif(@$_POST["reject1"])
		{
			$response = "qrReject";
			$sqlStatus = 2;
		}
		elseif(@$_POST["reject2"])
		{
			$response = "qrSwapIMEI";
			$sqlStatus = 4;
		}

		if(@$_POST["newimei"])
		{
			$imei = $_POST["newimei"];

		}
		elseif($response)
		{
			if($tc->WSSendResponse($response))
			{
				mysql_query("
						UPDATE `services_receive_message`
						SET `accepted` = '".$sqlStatus."', `accepteddate` = NOW()
						WHERE `id` = '".$offeranswer."' ;
				");
				if($sqlStatus == 4)
				{
					mysql_query("
						INSERT IGNORE INTO `cc_tasks_serial_replacement` (`task_id`) VALUES ('".$task_id."');
					");
				}
				echo '<font color"#336600">Svar afsendt til Telecare</font>';
			}
			else
			{
				echo '<font color"#FF0000">Svar til Telecare fejlede prøv igen om lidt</font>';
			}
		}
		else
		{
			echo '<font color"#FF0000">Svar til Telecare fejlede prøv igen om lidt</font>';
		}
		header("Location: /index.php?p=./modules/callcenter/task_invoice&task_id=".$task_id);
	}

	if(@$_POST["serviceid"] == 59013)
	{
		require_once('system/mail/htmlMimeMail.php');
		$mail = new htmlMimeMail();
		$mail->setFrom(ORDER_FROM_EMAIL_ADDRESS);
		$regards = "\n\nMed venlig hilsen\n\nSecurator A/S\nNydamsvej 17\n8362 Hørning\n\nTlf: 70 22 07 30";
		/*
		1: 'Godkendt'					<input type="submit" name="accept" value="Godkend tilbud" />
		2: 'Afvist og retur til kunden'	<input type="submit" name="reject1" value="Afvis og retur" />
		3: 'Afvist og skrottet'			<input type="submit" name="reject2" value="Afvis og skrottet" />
		*/
		if(@$_POST["reject1"])
		{
			$mail->setSubject("Tilbud afvist for ordre ".$task_id);
			$mailtext = "Tilbud på opgave '".$task_id."' : Afvist og retur".$regards;
			mysql_query("
				UPDATE `services_receive_message`
				SET `accepted` = '2', `accepteddate` = NOW()
				WHERE `id` = '".(int)$_POST["offeranswer"]."' ;
			");
			mysql_query("
				DELETE FROM `cc_tasks_closed_data_invoices` WHERE
				task_id='".$task_id."' AND invoice_type='EXPENSE'
				AND `servicepartner`=67;
			");
			offerEntry($task_id, "Tilbud afvist for ordre ".$task_id);
		}
		else if(@$_POST["reject2"])
		{
			$mail->setSubject("Tilbud afvist for ordre ".$task_id);
			$mailtext = "Tilbud på opgave '".$task_id."' : Afvist og skrottet".$regards;
			mysql_query("
				UPDATE `services_receive_message`
				SET `accepted` = '3', `accepteddate` = NOW()
				WHERE `id` = '".(int)$_POST["offeranswer"]."' ;
			");
			mysql_query("
				DELETE FROM `cc_tasks_closed_data_invoices` WHERE
				task_id='".$task_id."' AND invoice_type='EXPENSE'
				AND `servicepartner`=67;
			");
			offerEntry($task_id, "Tilbud afvist for ordre ".$task_id);
		}
		else
		{
			$bill_paid = db_get("SELECT `status` FROM `ordersystem` WHERE `reference` LIKE '".$task_id."'");
			if($bill_paid["status"] == "Paid")
			{
				$mail->setSubject("TILBUD på opgave '".$task_id."' er godkendt og selv risiko betalt. (".date("dmY").")");
				$mailtext  = "TILBUD på opgave '".$task_id."' er godkendt og selv risiko betalt\n";
				$mailtext .= $regards;
			}
			else
			{
				$mail->setSubject("Tilbud godkendt for ordre ".$task_id.".  Må ikke sendes. (".date("dmY").")");
				$mailtext = "TILBUD på opgave '".$task_id."' er godkendt";
				$mailtext .= $regards;
			}
			mysql_query("
				UPDATE `services_receive_message`
				SET `accepted` = '1', `accepteddate` = NOW()
				WHERE `id` = '".(int)$_POST["offeranswer"]."' ;
			");
			offerEntry($task_id, "Tilbud godkendt for ordre ".$task_id, 0);
		}
		$mail->setText($mailtext);
		$mail->setHtml(str_replace("\n", "<br />\n", $mailtext));
		$res = $mail->send(array("forsikring@n1s.dk"));
		$mail = null;
		header("Location: /index.php?p=./modules/callcenter/task_invoice&task_id=".$task_id);
	}

	if(@$_POST["serviceid"] == 100667)
	{
		$ecAccountQuery = db_get("
			SELECT sa.groups FROM
				`services_receive_message` srm
			LEFT JOIN
				`cc_tasks` ct ON ct.task_id = srm.repnr
			LEFT JOIN
				`products_customers` pc ON pc.rec_id=ct.customer_product_id
			LEFT JOIN
				`swc_account` sa ON sa.account_id=pc.dealer_id
			WHERE
				srm.id = '".(int)$_POST["offeranswer"]."'
		");
		$ecAccount = explode(",", trim($ecAccountQuery["groups"], ","));


		$externalid = (int)$_POST["externalid"];
		if(@$_POST["reject1"])
		{
			$status = "0";
			$statusTxt = "Tilbud afvist - Retur til kunden";
		}
		elseif(@$_POST["reject2"])
		{
			$status = "1";
			$statusTxt = "Tilbud afvist - Totalskadet";
		}
		else
		{
			$status = "2";
			$statusTxt = "Tilbud godkendt";
		}
		if($_POST["externalid"] > 0)
		{
			$statusText = db_get("SELECT `status` FROM `services_receive_message` WHERE `id` = '".(int)$_POST["offeranswer"]."'; ");

			$ec = new ECWebservice($task_id);
		 	if(in_array("27", $ecAccount))
		 	{
		 		$ec->changeLoginToFona();
		 	}
			$continueOffer = false;
			if(stristr($statusText["status"], 'debit'))
			{
				if($ec->acceptDebOffer($externalid, $status) == true)
				{
					$continueOffer = true;
				}
			}
			else
			{
				if($ec->acceptOffer($externalid, $status) == true)
				{
					$continueOffer = true;
				}
			}
			if($continueOffer == true)
			{
				mysql_query("
					UPDATE `services_receive_message` SET `accepted` = '".((int)$status+1)."',
					`accepteddate` = NOW() WHERE `id` = '".(int)$_POST["offeranswer"]."';
				");
				offerEntry($task_id, $statusTxt." ".$task_id);

				$ts = db_get("SELECT external_repair_id FROM `cc_tasks` WHERE task_id='".$task_id."' ");

				if($status == "2")
				{
					$ec1 = new ECWebservice($task_id);
				 	if(in_array("27", $ecAccount))
				 	{
				 		$ec1->changeLoginToFona();
				 	}
					$ecdata = $ec1->getRepInfo($ts["external_repair_id"] );
					$pay = $ecdata->outRepInfo;

					$cost_transport = $pay->fragt+$pay->transport;
					$cost_parts = $pay->fakEmballage+$pay->matBrutto;
					$cost_wages = $pay->arbejdsomk+$pay->grundtakst;
					$cost_total = $pay->subtot+$pay->moms;
					$remark = "";
					foreach ($ecdata->OutFakTxtInfo as $fa)
					{
						$remark .= $fa->fejlTekst."<br />";
					}
					mysql_query("
						INSERT INTO `cc_tasks_closed_data_invoices`
						(
							`task_id`, `invoice_type`, `create_date`, `update_date`,
							`servicepartner`, `cost_total`, `cost_transport`,
							`cost_wages`, `cost_parts`, `remark`
						)
						VALUES
						(
							'".$task_id."', 'EXPENSE', NULL, NULL, 18,
							'".$cost_total."', '".$cost_transport."',
							'".$cost_wages."', '".$cost_parts."',
							'".mysql_real_escape_string($remark)."'
						);
					");
					$ec = null;
					$ecdata = null;
					$pay = null;
				}
				header("Location: /index.php?p=./modules/callcenter/task_invoice&task_id=".$task_id);
			}
			else
			{
				die("Godkendelse af tilbud fejlede ved EC<br /><br />Prøv igen om lidt.");
			}
		}
	}
}


if (@$_POST["sendSmsToCustomer"])
{
	$status_log = array();
	if (@$_POST["sendMail"])
	{
		$hilsen = "<br /><br />Med venlig hilsen<br /><br />Securator A/S<br />Nydamsvej 17<br />8362 Hørning<br /><br />Tlf: 70 22 07 30";
		$getTitle = db_get("SELECT * FROM `skabeloner` WHERE id='".(int)$_POST['standardText']."' Limit 1; ");
		require_once('system/mail/htmlMimeMail.php');
		$mail = new htmlMimeMail();
		$mail->setFrom(ORDER_FROM_EMAIL_ADDRESS);
		$mail->setSubject("Status fra Securator A/S - Opgave " . $task_id);
		$mail->setHtml(str_replace("\n","<br />", $_POST["message"]).$hilsen, NULL, SERVER_ROOT);
		$res = $mail->send(array($sendTo["email"]));
		$status_log["mail"] = $sendTo["email"];
	}

	if (@$_POST["sendSMS"])
	{
		$hilsen = " Mvh Securator A/S - Tlf: 70 22 07 30
Denne besked kan ikke besvares.";
		ModuleLoadInterface("smsgateway");
		if(strlen($_POST["message"]) > 5)
		{
			$result = SendSMS($account->id, $sendTo["mob"], $_POST["message"].$hilsen);
			$status_log["mobil"] = $sendTo["mob"];
		}
	}
	$query = "INSERT INTO `status_messages_sent` (`id`, `task_id`, `timedate`, `tekst`, `tosms`, `tomail`)
	VALUES (null, '".$task_id."', NOW(), '".mysql_real_escape_string($_POST["message"])."', '".( @$_POST["sendSMS"] == "sendSMS" ? $sendTo["mob"]: "" )."', '".( @$_POST["sendMail"] == "sendMail" ? $sendTo["email"]: "" )."');";
	@mysql_query($query) or die(mysq_error());
	header("Location: ./index.php?p=./modules/callcenter/task_entries&task_id=".$task_id);
}

function __UploadFile($file_name, &$data, $customer_id)
{
	if (isset($_FILES[$file_name]))
	{
		if ($_FILES[$file_name]['size'] > 0)
		{
			$fu = new FileUpload(15360000);
			if ($fu->Upload($file_name))
			{
				// Det nye filstruktur integration.
				require_once("class/securator.system.class.php");
				$ssc = new securator_system_class($customer_id);
				$ssc->createUserFolder();
				$ssc_newPath = $ssc->newpath();

				$path = SERVER_ROOT."data/account".$ssc_newPath."/".$customer_id;
				$web_root = "data/account".$ssc_newPath."/".$customer_id.'/';
				if ($fu->SaveFile($path, 1))
				{
					db_query("INSERT INTO cc_tasks_documents (task_id, entry_id, rec_id, document_path, created, account_id, customer_id) " .
							 "VALUES ('".$data['task_id']."','".$data['rec_id']."','','$web_root".$fu->file['name']."', NOW(), '".$data['account_id']."', '$customer_id') ");
				}
			}
		}
	}
}

/**
 * Functioner til Form
 * @param $action
 * @param $data
 */
function OnUpdateData($action, &$data)
{
	global $account, $cc;
	$task_id = $data["task_id"];
	mysql_query("UPDATE `cc_tasks` SET `status`='100' WHERE `task_id`='$task_id'; ") or die(mysql_error());
	$insertdata["task_id"] = $task_id;
	$insertdata["account_id"] = $account->id;
	$insertdata["created"] = date("Y-m-d H:i:s");
	$insertdata["subject"] = "Sag afsluttet";
	$insertdata["note"] = $data["notat"];
	$insertdata["status"] = 100;
	$task_id = db_insert_record($insertdata, "cc_tasks_entry");
	db_query("UPDATE cc_tasks SET status='100' WHERE task_id='$task_id' ");
	$cc->FinishTask($task_id);
	header("Location: ./index.php?p=./modules/callcenter/task_list&task_type=1");
}

function OnTaskEntryPost($action, &$data)
{
	global $last_insert_id, $update_product;
	$res = POST_TYPE_NONE;
	if($data["service_id"] > 0 || $data["repair_id"] > 0)
	{
		db_query("UPDATE `cc_tasks` SET `task_type` = '1' WHERE `task_id` = '".$data["task_id"]."';");
	}

	switch($action)
	{
		case POST_TYPE_UPDATE:
			$res = FormDBUpdate($data);
			if ($res == POST_TYPE_UPDATE)
			{
				$task = db_get("SELECT * FROM cc_tasks WHERE task_id=".$data['task_id']);
				__UploadFile('_file_1', $data, $task['customer_id']);
				__UploadFile('_file_2', $data, $task['customer_id']);
				__UploadFile('_file_3', $data, $task['customer_id']);
			}
			break;

		case POST_TYPE_INSERT:
			$res = FormDBAdd($data);
			if ($res == POST_TYPE_INSERT)
			{
				$data['rec_id'] = $last_insert_id;
				$task = db_get("SELECT * FROM cc_tasks WHERE task_id=".$data['task_id']);
				__UploadFile('_file_1', $data, $task['customer_id']);
				__UploadFile('_file_2', $data, $task['customer_id']);
				__UploadFile('_file_3', $data, $task['customer_id']);

				if($data["service_id"] == "26" && $data["status"] == "40")
				{
					header("Location: /index.php?p=./modules/callcenter/create_pickup_sec&task_id=" . $data['task_id']);
				}
				if($data["service_id"] == "70" && $data["status"] == "50")
				{
					header("Location: /index.php?p=./modules/callcenter/create_task_ec_return&task_id=" . $data['task_id']);
				}
				elseif($data['service_id'] == "70" && $data["status"] == "40")
				{
					header("Location: /index.php?p=./modules/callcenter/create_task_ec&task_id=".$data['task_id']);
				}
				elseif($data['service_id'] == "70" && $data["status"] == "80")
				{
					$repnrSql = db_get("SELECT external_repair_id FROM cc_tasks WHERE task_id='".(int)$data["task_id"]."';");
					$ec = new ECWebservice((int)$data["task_id"]);
					$repid2 = $ec->opretOmbytning($repnrSql["external_repair_id"]);
					if((int)$repid2 > 0)
					{
						db_query("UPDATE `cc_tasks` SET `external_repair_id2` = '".$repid2."' WHERE `task_id` ='".$data["task_id"]."';");
						header("Location: ./index.php?p=./modules/callcenter/task_entries&task_id=".$data["task_id"]);
					}
					else
					{
						echo "Oprettelse af reparationsid ved EC fejlede, prøv venligst igen senere.";
					}
				}
				if($data["status"] == "100")
				{
					header("Location: /index.php?p=./modules/callcenter/task_invoice&task_id=".$data["task_id"]);
				}
			}
			break;

		default:
			break;
	}
	return $res;
}

function OnTaskExcessCreate($action, &$data)
{
	global $task_id, $account, $cc;

	if($action == POST_TYPE_INSERT)
	{
		/**
		 * Selvrisiko oprettelse
		 */
		include 'system/ordersystem.php';
		$os = new ordersystem();

		$task_data = db_get("SELECT customer_id FROM `cc_tasks` WHERE `task_id` = '".$task_id."'; ");

		$excess_text = db_get("
			SELECT
				excess_text, excess_amount
			FROM
				`products_insurance_excess`
			WHERE
				excess_id = '".(int)$data["excess_id"]."';
		");
		$customer_id = $task_data["customer_id"];


		/**
		 *Opdatering af selvrisiko på indtægten
		 */
		$sql_income_id = "
			SELECT
				rec_id
			FROM
				`cc_tasks_closed_data_invoices`
			WHERE
				`task_id` = '".$task_id."' AND
				`invoice_type` = 'INCOME'
		";
		$rec_data = db_get($sql_income_id);

		if($rec_data == false)
		{
			db_query("
				INSERT INTO `cc_tasks_closed_data_invoices`
					(`task_id` ,`invoice_type`)
				VALUES
					('".$task_id."', 'INCOME');
			");
			$rec_data = db_get($sql_income_id);
		}
		db_query("
			UPDATE
				`cc_tasks_closed_data_invoices`
			SET
				`income_excesses` = '".$excess_text["excess_amount"]."'
			WHERE
				task_id = '".$task_id."';
		");
		/**
		 * Slut på opdatering af selvrisiko.
		*/


		$orderid = $os->createOrder($customer_id, $account->id, 10, $task_id);
		$os->createOrderLine($orderid, 0, $excess_text["excess_text"], "", 1, 0, $excess_text["excess_amount"], 0, 0);
		$os->updateOrderPayment($orderid);

		ModuleLoadInterface("ordersystem");
		$generateInvoice = generateInvoice($orderid);

		$url  = "http://backup.securator.dk/index.php?";
		$url .= "p=./modules/ordersystem/sendinvoice&invoice=".$orderid;
		fetchUrl($url);

		/**
		 * Entry oprettelse
		 */
		$insertdata["task_id"] = $task_id;
		$insertdata["account_id"] = $account->id;
		$insertdata["created"] = date("Y-m-d H:i:s");
		$insertdata["subject"] = mysql_real_escape_string($_POST["task_status_text"]);
		$insertdata["note"] = mysql_real_escape_string($_POST["task_status_text"]);
		$insertdata["note_type_id"] = 3;
		$insertdata["status"] = (int)$_POST["task_status"];
		$insertdata["service_id"] = (int)$_POST["task_service_center"];
		$insertdata["repair_id"] = (int)$_POST["task_repair_center"];
		db_insert_record($insertdata, "cc_tasks_entry");

		/**
		 * Opgave reaktion opdatering
		 */
		$reaction = strtotime($cc->GetNextResponse((int)$_POST["task_status"]));
		if(date("N", $reaction) == 6)
		{
			$reaction=$reaction+(86400*2);
		}
		else if(date("N", $reaction) == 7)
		{
			$reaction=$reaction+(86400*2);
		}

		db_query("
			UPDATE
				cc_tasks
			SET
				status='".(int)$_POST["task_status"]."',
				next_response='".date("Y-m-d H:i:s", $reaction)."'
			WHERE
				task_id=".(int)$task_id.";
		");


		/**
		 * Sms / Mail Sender
		 */
		@$sms_send_sms = $_POST["sms_send_sms"];
		@$sms_send_mail = $_POST["sms_send_mail"];

		if($sms_send_mail || $sms_send_sms)
		{
			$sendTo = db_get("
				SELECT
					sa.email, REPLACE(saab.mobile_phone, ' ', '') AS mob
				FROM
					`cc_tasks` cct
				LEFT JOIN
					`swc_account` sa ON cct.customer_id = sa.account_id
				LEFT JOIN
					`swc_account_address_book` saab ON cct.customer_id = saab.account_id
				WHERE
					cct.`task_id` = '".$task_id."';
			");

			$sendToMes = db_get("SELECT `message` FROM `skabeloner` WHERE `id`='".(int)$_POST["smsMessage"]."'; ");

			$sendToMail = $sendTo["email"];
			$sendToMobil = $sendTo["mob"];
			$sendToMessage = $sendToMes["message"];
		}

		if(strlen($sendToMessage) > 5)
		{
			if ($sms_send_mail)
			{
				$hilsen = "<br /><br />Med venlig hilsen<br /><br />Securator A/S<br />Nydamsvej 17<br />8362 Hørning<br /><br />Tlf: 70 22 07 30";
				$getTitle = db_get("SELECT * FROM `skabeloner` WHERE id='".(int)$_POST['standardText']."' Limit 1; ");
				require_once('system/mail/htmlMimeMail.php');
				$mail = new htmlMimeMail();
				$mail->setFrom(ORDER_FROM_EMAIL_ADDRESS);
				$mail->setSubject("Status fra Securator A/S - Opgave " . $task_id);
				$mail->setHtml(str_replace("\n","<br />", $sendToMessage).$hilsen, NULL, SERVER_ROOT);
				$res = $mail->send(array($sendToMail));
			}

			if ($sms_send_sms)
			{
				$hilsen = " Mvh Securator A/S - Tlf: 70 22 07 30";
				ModuleLoadInterface("smsgateway");
				$result = SendSMS($account->id, $sendToMobil, $sendToMessage.$hilsen);
			}

			if($sms_send_sms || $sms_send_mail)
			{
				db_query("
					INSERT INTO `status_messages_sent`
					(
						`task_id`, `timedate`,
						`tekst`, `tosms`, `tomail`
					)
					VALUES
					(
						'".$task_id."', NOW(),
						'".mysql_real_escape_string($sendToMessage)."',
						'".( $sendToMobil ? $sendToMobil : "" )."',
						'".( $sendToMail ? $sendToMail : "" )."'
					);
				");
			}
		}
		header("Location: /index.php?p=./modules/ordersystem/sendinvoice&invoice=".$orderid);
	}

}

/**
 * Bruges til opgaveændring formularen
 */
function OnTastStatusChange($action, &$data)
{
	global $account;
	switch ($action)
	{
		case POST_TYPE_INSERT:

			// Opdatering af status
			$updateStatusData["task_id"] = $data["task_id"];
			$updateStatusData["status"] = (int)$data["status_new"];
			$updateStatusData["task_type"] = (int)$data["task_type"];
			db_update_record($updateStatusData, "cc_tasks", "task_id");

			if(@$data["limit"])
			{
				$updateData["task_id"] = $data["task_id"];
				$updateData["compensation_limit"] = MoneyToMySQL($data["limit"]);
				$updateData["product_number"] = $data["product_no"];
				$updateData["message"] = ''.mysql_real_escape_string($data["message"]);

				$exists = db_get("SELECT * FROM cc_tasks_compensation WHERE task_id = '".$data["task_id"]."'; ");
				if($exists)
					db_update_record($updateData, "cc_tasks_compensation", "task_id");
				else
					db_insert_record($updateData, "cc_tasks_compensation");

				$entry["task_id"] = $data["task_id"];
				$entry["account_id"] = $account->id;
				$entry["created"] = date("Y-m-d H:i:s");
				$entry["subject"] = "Erstatningslimit sendt";
				$entry["note"] = "Erstatningslimit: ".$data["limit"]."\nProduktnummer: ".$data["product_no"]."\nBesked: ".$data["message"]."\n";
				$entry["status"] = 100;
				$entry["note_type_id"] = 1;
				$entry["entry_visibility"] = 50;
				db_insert_record($entry, "cc_tasks_entry");
			}

			// Annullerer policen hvis den skal det
			if(@$data["policy_cancel"])
			{
				$policy_data = db_get("
					SELECT
						pip.rec_id
					FROM
						`cc_tasks` ct
					LEFT JOIN
						`products_insurance_policies` pip
					ON
						pip.`product_customers_id` = ct.customer_product_id
					WHERE
						ct.task_id = '".(int)$data["task_id"]."' ;
				");
				$policyUpdate["rec_id"] = (int)$policy_data["rec_id"];
				$policyUpdate["status"] = "EndedWithNote";
				$policyUpdate["note_account_id"] = $account->id;
				$policyUpdate["CanceledDate"] = date("Y-m-d");
				$policyUpdate["CanceledText"] = "9";
				db_update_record($policyUpdate, "products_insurance_policies", "rec_id");
			}

			// Redirects til omkostninger hvis den skal det.
			if(@$data["type_expenses"])
			{
				header("Location: /index.php?p=./modules/callcenter/task_invoice&task_id=".(int)$data["task_id"]);
			}
			break;

		case POST_TYPE_UPDATE:

			$updateStatusData["task_id"] = $data["task_id"];
			$updateStatusData["status"] = (int)$data["status_new"];
			$updateStatusData["task_type"] = (int)$data["task_type"];
			db_update_record($updateStatusData, "cc_tasks", "task_id");

			if(@$data["limit"])
			{
				$updateData["task_id"] = $data["task_id"];
				$updateData["compensation_limit"] = MoneyToMySQL($data["limit"]);
				$updateData["product_number"] = $data["product_no"];
				$updateData["message"] = mysql_real_escape_string($data["message"]);
				db_update_record($updateData, "cc_tasks_compensation", "task_id");

				$entry["task_id"] = $data["task_id"];
				$entry["account_id"] = $account->id;
				$entry["created"] = date("Y-m-d H:i:s");
				$entry["subject"] = "Erstatningslimit sendt";
				$entry["note"] = "Erstatningslimit: ".$data["limit"]."\nProduktnummer: ".$data["product_no"]."\nBesked: ".$data["message"]."\n";
				$entry["status"] = 100;
				$entry["note_type_id"] = 1;
				$entry["entry_visibility"] = 50;
				db_insert_record($entry, "cc_tasks_entry");
			}
			break;
	}
}

/**
 * Slut for funktioner til forms
 */


if (@$_GET['send_report'] == 'true')
{ // send report to repair center has been selected

	require_once('system/mail/htmlMimeMail.php');
	$mail = new htmlMimeMail;
	$mail->setFrom(WEBSITE_FROM_EMAIL_ADDRESS);

	// attach pdf
	$pdfpoint = fopen(SERVER_ROOT.$_SESSION['task_report']['pdf_generated'], 'r');
	$pdfdata = fread($pdfpoint, filesize(SERVER_ROOT.$_SESSION['task_report']['pdf_generated']));
	$mail->addAttachment($pdfdata, "rapport.pdf", "application/octet-stream", "base64");

	$rcpt = $_SESSION['task_report']['repair_center_email'];

	if (isset($rcpt) && !empty($rcpt))
	{ // make a double check that rcpt is set, else throw error
		define('MAIL_RECIPIENT_ACCEPTED', true);
	}
	else
	{
		define('MAIL_RECIPIENT_ACCEPTED', false);
	}

	$template = new Template;
	$template->Load('callcenter', 'CALLCENTER_EMAIL_REPORT');

	$task_id = $_SESSION['task_report']['task_id'];

	$txt = $template->Render('callcenter');
	$tit = sprintf($template->template_data['title'], $_SESSION['task_report']['task_id']);
	$mail->setSubject($tit);
	$mail->setHtml(nl2br($txt), NULL, SERVER_ROOT.'images/');
	if (MAIL_RECIPIENT_ACCEPTED === true) {
		if ($mail->send(array($rcpt)))
		{
			db_query("UPDATE cc_tasks_reports SET emailed=NOW(), email_rcpt='".$_SESSION['task_report']['repair_center_email']."', email_name='".$_SESSION['task_report']['repair_center_name']."' WHERE task_id='".$_SESSION['task_report']['task_id']."' AND rec_id='".$_SESSION['task_report']['rec_id']."' LIMIT 1");
			db_query("INSERT INTO cc_tasks_reports_sent (task_id, report_id, rec_id, email, service_center_id, sent) VALUES('".$_SESSION['task_report']['task_id']."', '".$_SESSION['task_report']['rec_id']."', '', '".mysql_real_escape_string($_SESSION['task_report']['repair_center_email'])."', '".$_SESSION['task_report']['repair_center_id']."', NOW()) ");
		}
		else
		{
			$GLOBALS['act'] = array('send_pdf' => 'EMAIL_POLICY_SENT_FAIL');
		}
	}
	else
	{
		$GLOBALS['act'] = array('send_pdf' => 'EMAIL_POLICY_SENT_FAIL');
	}
	unset($_SESSION['task_report']);
	header("Location: /index.php?p=./modules/callcenter/task_entries&task_id=" . $task_id);
}


$res = FormHandlePost();


if ($res == POST_TYPE_INSERT)
{
	if (@$_POST['_next_response'] != '')
		db_query("UPDATE cc_tasks SET status='".$_POST['status']."', next_response='".DateToMySQL($_POST['_next_response'], constant(SYSTEM_LANGUAGE.'_DATETIME_FORMAT'),'Y-m-d H:i:s')."' WHERE task_id=".$_POST['task_id']." ");
	else
		db_query("UPDATE cc_tasks SET status='".$_POST['status']."' WHERE task_id=".$_POST['task_id']." ");

	if (@$_POST['note_type_id'] == TASK_NOTE_TYPE_CUSTOMER_CONTACT)
		db_query("UPDATE cc_tasks SET last_customer_contact=NOW() WHERE task_id='".$_POST['task_id']."' ");

	if ($_POST['status'] == TASK_STATUS_CLOSED)
	{ // release lend out product
		$cc->FinishTask($_POST['task_id']);
	}

	if (@$_POST['responsible_id'])
	{
		// Send an email to the person
		require_once('system/mail/htmlMimeMail.php');
		$data = $cc->TaskReport($task_id, 100, true);
		$mail = new htmlMimeMail();
		$mail->setFrom(WEBSITE_FROM_EMAIL_ADDRESS);
		$mail->setSubject("Sag $task_id rapport - Securator A/S");
		$mail->setHtml($data, NULL, SERVER_ROOT);
		$email = db_get("SELECT email FROM swc_account WHERE account_id='".$_POST['responsible_id']."' ");
		if (@$email['email'] != '') {
			$to_emails[] = $email['email'];
			$mail_sent = $mail->send($to_emails);
		}
	}
}

/*
 * Bruges til at skubbe opgaverne væk fra weekenderne..
 */

if ($res == POST_TYPE_DATA_UPDATE)
{

	if($_POST["_form_id"] == "TASK_ENTRY" || $_POST["_form_id"] == "TASK_ENTRY_EDIT")
	{
		$doedit = 1;
		@$docreate = $_POST['_is_create'];
		$task_id=(int)@$_POST['task_id'];

		$stat = db_get("SELECT * FROM cc_status WHERE status_id='".@$_POST['status']."'");
		if ($stat)
		{
			$reaction = strtotime($cc->GetNextResponse($stat['status_id']));
			$_POST['_next_response'] = date("Y-m-d H:i:s", $reaction);
		}
		$cc_entry = $_POST;
	}
}

$s = new Securator();
$s->updateTaskData($task_id);

if (isset($_GET['act']))
{
	switch ($_GET['act'])
	{
		case 'create':
			global $account;
			$cc_entry = db_get_empty_record('cc_tasks_entry');
			$cc_entry['task_id'] = $task_id;
			$cc_entry['created'] = date('Y-m-d H:i:s');
			$cc_entry['account_id'] = $account->id;
			$docreate = 1;
			$doedit = 1;
			break;

		case 'edit':
			$doedit = 1;
			break;

		case 'delete':
			 db_query("
			 	DELETE FROM
			 		cc_tasks_entry
			 	WHERE
			 		task_id='".$task_id."' AND
			 		rec_id='".$rec_id."'
			 ");
			break;

		case 'deleteAttach':
			$deletedata = unserialize(base64_decode($_GET["deletedata"]));
			if(is_file($deletedata["document_path"])){
				@unlink($deletedata["document_path"]);
				db_query("
					DELETE FROM
						cc_tasks_documents
					WHERE
						task_id='".mysql_real_escape_string($deletedata["task_id"])."' AND
						entry_id='".mysql_real_escape_string($deletedata["entry_id"])."' AND
						rec_id='".mysql_real_escape_string($deletedata["rec_id"])."' AND
						document_path='".mysql_real_escape_string($deletedata["document_path"])."';
				");
				header("Location: ./index.php?p=./modules/callcenter/task_entries&task_id=".$deletedata["task_id"]);
			}

		default:
			break;
	}
	unset($_GET['act']);
}

if(@$_POST["manualupload"] == "true")
{
	__UploadFile("file1", $data, $task['customer_id']);
	header("Location: ./index.php?p=./modules/callcenter/task_entries&task_id=".$task_id);
}


//
// Start page output
//
//
//


@$task_reports = array();
$task_tasks_query = db_fetch_array("SELECT type FROM cc_tasks_reports WHERE task_id='$task_id'");
if(count($task_tasks_query))
{
	foreach ($task_tasks_query as $t)
	{
		$task_reports[] = $t["type"];
	}
}

echo "<div style=\"float:right;\">";
echo FunctionButton("Dækning", "", "javascript:toggleLayer('coverage');");
echo "</div>";


$task_data = db_get("SELECT * FROM cc_tasks WHERE task_id='$task_id'; ");
$task_closed = db_get("SELECT * FROM cc_tasks_closed_data WHERE task_id='$task_id'; ");
$userdata = db_get("SELECT * FROM swc_account_address_book WHERE account_id='".$task_data["customer_id"]."'; ");

/*
if(@$userdata["home_insurance_company"] == "" || @$userdata["home_insurance_company"] == 0)
{
	echo "<div style=\"text-align:center;\"><h2><font color=\"#FF0000\">Husk at få kundens indbo forsikringsselskab</font></h2></div>";
}
*/

if($userdata["tmpaccount"] == 1)
{
	echo '<center><font color="#F00" style="font-size:16px;"><b>Midlertidig konto</b></font><br /><br />Find kundens konto og flyt alle oplysningerne. - ';
	echo '<a href="/index.php?p=./modules/callcenter/administration&act=move_task_product&cust_id='.$userdata["account_id"].'">Flyt data</a><br />';
	echo '</center><br />';
}

echo '<div id="coverage" style="display:none;">';
echo '<fieldset>';
$coverageData = db_get("
	SELECT
		pict.*
	FROM
		cc_tasks ct
	LEFT JOIN
		`products_customers` pc ON pc.rec_id=ct.customer_product_id
	LEFT JOIN
		products_insurance_group `pig` ON pc.insurance_group_id=pig.group_id
	LEFT JOIN
		products_insurance_coverage `pic` ON `pic`.`id`=`pig`.`coverage_id`
	LEFT JOIN
		products_group pg ON pc.product_group_id=pg.group_id

	LEFT JOIN
		`products_insurance_coverage_texts` pict ON pict.coverage_id=pig.coverage_id AND pict.class_id=pg.class_id
	WHERE
		`ct`.`task_id`='".$task_id."' LIMIT 1;
");
echo '<table width="100%"><tr>';
echo '<td valign="top" width="50%"><h2><u>Dækket</u></h2>'.$coverageData["covered"].'</td>';
echo '<td valign="top"><h2><u>Ikke Dækket</u></h2>'.$coverageData["uncovered"].'</td></tr></table>';
echo '</fieldset></div>';


if (!$cc->TaskIsOpen($task_id))
	$cc->DrawLockBox($task_id);

if($userdata["tmpaccount"] == 0)
{


	echo "<div style=\"float:left;\">";
	echo PageFunctionButton(_LANG('callcenter', 'customer_card'), "&account_id=".$task_data['customer_id']."", "./modules/callcenter/customer");
	echo "</div>";
	if($doedit == 0)
	{
		echo "<div style=\"float:right;\">";
		echo PageFunctionButton("Opgave forlob", "&task_id=$task_id", "./modules/callcenter/task_status");
		echo FunctionButton("Send status til kunden", "", "javascript:toggleLayer('statusToCustomer');");
		echo FunctionButton("Faktura til kunden", "", "javascript:toggleLayer('ordreforms');");
		echo FunctionButton("Opret selvrisiko", "", "javascript:toggleLayer('createExcess');");
		echo "</div>";
	}
}
else
{
	echo "<div style=\"float:left;\">";
	echo PageFunctionButton(_LANG('callcenter', 'customer_card'), "&account_id=".$task_data['customer_id']."", "./modules/callcenter/customer");
	echo "</div>";
}


echo '<br /><table cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td valign="top">';

$external_partner = (int)$userdata["external"];

/*
 * Functions to make javascript data for invoice and sms drop-down.
 */
function statusMessagesList()
{
	echo "var mineSkabeloner=new Array();";
	$query = mysql_query("SELECT * FROM `skabeloner` ");
	while( ($row = mysql_fetch_assoc($query)) != FALSE )
	{
		echo "mineSkabeloner[".$row["id"]."]=\"".mysql_real_escape_string($row["message"])."\";";
	}
}

function predefinedOrderlinesList()
{
	global $external_partner;
	echo "var predefinedList=new Array();";
	$query = mysql_query("SELECT * FROM `ordersystem_predefined_lines` WHERE task_page=1 ORDER BY desc_text");
	while( ($row = mysql_fetch_assoc($query)) != FALSE )
	{
		echo "predefinedList[".$row["id"]."]=\"".mysql_real_escape_string($row["price"])."\";";
	}
}

function statusMessagesOptions()
{
	$query = mysql_query("SELECT * FROM `skabeloner` ORDER BY name ");
	while( ($row = mysql_fetch_assoc($query)) != FALSE )
	{
		echo "<option value=\"".$row["id"]."\">".$row["name"]."</option>";
	}
}

function predefinedOrderlines()
{
	global $external_partner;
	$query = mysql_query("SELECT * FROM `ordersystem_predefined_lines` WHERE task_page=1 ORDER BY desc_text ");
	while( ($row = mysql_fetch_assoc($query)) != FALSE )
	{
		echo "<option value=\"".$row["type"]."-".$row["id"]."\">".$row["desc_text"]."</option>";
	}
}

?>

<style>
fieldset
{
	border-color: #4A5C68;
	border-style: solid;
}
</style>

<script type="text/javascript">

<?php statusMessagesList(); ?>
<?php predefinedOrderlinesList(); ?>


var oln = 0;
function addtext(no)
{
	var newtext = mineSkabeloner[no];
	document.sendSkabelon.message.value = newtext;
}

function insertOldSchool(theSel, newText, newValue)
{
	var values=newValue.split("-");
	if(newValue.length > 2)
	{
		oln++;
		var obj = document.getElementById("choosenOrderlines");
		var line  = '<table width="450" id="choosenOrderlines_'+oln+'"><tr>';
		line += '<input type="hidden" name="lines['+oln+']" value="'+newValue+'">';
		line += '<td>'+newText+'</td>';
		line += '<td width="120"><input type="text" name="notes['+oln+']" value=""></td>';

		if(values[0] == 1)
			line += '<td width="100"><input type="text" name="kr['+oln+']" size="5" style="text-align:right;" value="0"><input type="text" name="ore['+oln+']" size="2" value="00"></td>';
		else
			line += '<td width="100" align="right">'+predefinedList[values[1]]+'</td>';

		line += '<td width="16"><img src="/style/icons/default/system/box_close.gif" onclick="removeElement(\'choosenOrderlines_'+oln+'\')" border="0"></td>';
		line += '</tr></table>';
		obj.innerHTML += line;
	}
}

function removeElement(divNum)
{
	var d = document.getElementById('choosenOrderlines');
	var olddiv = document.getElementById(divNum);
	d.removeChild(olddiv);
}

function removeOldSchool(theSel)
{
	var selIndex = theSel.selectedIndex;
	if (selIndex != -1)
	{
		for(i=theSel.length-1; i>=0; i--)
		{
			if(theSel.options[i].selected)
			{
				theSel.options[i] = null;
			}
		}
		if (theSel.length > 0)
		{
			theSel.selectedIndex = selIndex == 0 ? 0 : selIndex - 1;
		}
	}
}

</script>

<div id="ordreforms" style="display:none;">
<form class="form" action="index.php?p=./modules/ordersystem/createorder&task_id=<?php echo $task_id; ?>" name="formordre" method="POST" enctype="multipart/form-data">
<input type="hidden" name="task_id" value="<?php echo $task_id; ?>" />
<fieldset><legend><h2>Faktura til kunden</h2></legend>
<table width="100%" cellspacing="0" cellpadding="0" class="securator_box">
<tbody>
<tr class="securator_box_center_row">
	<td class="securator_box_center_left"></td>
	<td class="securator_box_center_center">
	<table width="100%" cellspacing="0" cellpadding="0">
	<tbody>
	<tr>
		<td>
			<table width="100%" cellspacing="4" cellpadding="0" border="0" class="form">
			<tbody>
			<tr class="form">
				<td class="form">Foruddefinerede Ordrelinjer</td>
				<td align="left" class="form">
					<select name="predefinedOrderlines[]" onchange="insertOldSchool(this.form.choosenOrderlines, this.options[this.selectedIndex].text, this.options[this.selectedIndex].value);">
						<option value="">Vælg Ordrelinje</option>
						<?php echo predefinedOrderlines(); ?>
					</select>
				</td>
			</tr>
			<tr class="form">
				<td class="form">Ordrelinier</td>
				<td align="left" class="form">
					<table width="450"><tr>
						<td><b>Orderlinje Tekst</b></td>
						<td align="center" width="120"><b>Notat</b></td>
						<td align="center" width="130"><b>Kr/Øre</b></td>
						<td width="16">&nbsp;</td>
					</tr></table>

					<div id="choosenOrderlines">
					</div>
					<input type="submit" value="Opret faktura" />
				</td>
			</tr>
			</tbody>
			</table>
		</td>
	</tr>
	</tbody>
	</table>
	</td>
	<td class="securator_box_center_right"></td>
</tr>
<tr class="securator_box_bottom_row">
	<td class="securator_box_bottom_left"></td>
	<td class="securator_box_bottom_center"></td>
	<td class="securator_box_bottom_right"></td>
</tr>
</tbody>
</table>
<?php

	$list = new ListBoxEx('ORDERSYSTEM_VIEW_ORDERS');
	$list->SetOrderBy("order_due");
	$list->SetWhere("account_id='".$task_data["customer_id"]."'");
	$list->SetWidth('100%');
	$list->SetFolderLink("/index.php?p=./modules/ordersystem/view");
	$list->Draw();

?>
</form></fieldset>
</div>



<?php
	// Send status til kunden
?>
<div id="statusToCustomer" style="display:none;">
<form class="form" action="index.php?p=./modules/callcenter/task_entries&task_id=<?php echo $task_id; ?>" name="sendSkabelon" method="POST" enctype="multipart/form-data">
<input type="hidden" name="sendSmsToCustomer" value="true" />
<fieldset><legend><h2>Send status til kunden</h2></legend>
<table width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr class="securator_box_center_row">
	<td class="securator_box_center_left"></td>
	<td class="securator_box_center_center">
	<table width="100%" cellspacing="0" cellpadding="0">
	<tbody>
	<tr>
		<td>
			<table width="100%" cellspacing="4" cellpadding="0" border="0" class="form">
			<tbody>
			<tr class="form">
				<td class="form">Mobilnummer&nbsp;&nbsp;</td>
				<td align="left" class="form">
					<select name="standardText" onchange="addtext(this.options[this.selectedIndex].value);">
						<option value="">Vælg besked</option>
						<?php echo statusMessagesOptions(); ?>
					</select>

				</td>
				<td rowspan="3" class="form">

				<input type="checkbox" name="sendSMS" value="sendSMS" <?php echo (strlen($sendTo["mob"]) == 8 ? "" : "disabled"  );  ?> /> SMS<br /><br />
				<input type="checkbox" name="sendMail" value="sendMail"  <?php echo (strlen($sendTo["email"]) > 5 ? "checked" : "disabled"  );  ?> /> E-Mail<br /><br />

				<input type="submit" value="send" />

				</td>
			</tr>
			<tr class="form">
				<td nowrap="" class="form">SMS Besked&nbsp;&nbsp;</td>
				<td align="left" class="form"><textarea style="border:1px solid #ccc;" cols="45" rows="4" id="message" name="message"></textarea></td>
			</tr>
			</tbody>
			</table>
		</td>
	</tr>
	</tbody>
	</table>
	</td>
	<td class="securator_box_center_right"></td>
</tr>
<tr class="securator_box_bottom_row">
	<td class="securator_box_bottom_left"></td>
	<td class="securator_box_bottom_center"></td>
	<td class="securator_box_bottom_right"></td>
</tr>
</tbody>
</table>
</form>
<h2> &nbsp; <b>Status log</b><h2>
<?php
	$list = new ListBoxEx('SKABELONER_LOG');
	$list->SetOrderBy("timedate");
	$list->SetWhere("task_id=$task_id");
	$list->SetWidth('100%');
	$list->Draw();
?>
</fieldset></div>




<?php
// Selvrisiko til kunden
?>
<div id="createExcess" style="display:none;">
<fieldset>
	<legend>
		<h2>Opret selvrisiko til kunden</h2>
	</legend>
	<?php

		$formArray = array("task_id" => $task_id, "sms_id" => 13);
		$f = new Form($formArray, 'TASK_EXCESS_CREATE', 1);
		$f->Draw();

	?>
	</form>
</fieldset>
</div>


<script language=javascript type='text/javascript'>
function toggleLayer( whichLayer )
{
	var elem, vis;
	if( document.getElementById )
		elem = document.getElementById( whichLayer );
	else if( document.all )
		elem = document.all[whichLayer];
	else if( document.layers )
		elem = document.layers[whichLayer];

	vis = elem.style;
	if(vis.display==''&&elem.offsetWidth!=undefined&&elem.offsetHeight!=undefined)
		vis.display = (elem.offsetWidth!=0&&elem.offsetHeight!=0)? 'block' : 'none';

	vis.display = (vis.display==''||vis.display=='block')?'none':'block';
}

</script>
<?

if (@$_SESSION['task_report'] && $_SESSION['task_report']['displayed'] != 'true')
{
	echo '<table cellpadding="0" cellspacing="0" width="100%"><tr><td width="100%" valign="top">';

	$mesg = '<table cellpadding="0" cellspacing="0" width="100%"><tr><td>'.sprintf(_LANG('callcenter', 'task_report_send_repair'), $_SESSION['task_report']['repair_center_name'], $_SESSION['task_report']['repair_center_email']);
	$mesg .= '</td><td>' . PageFunctionButton(_LANG('callcenter', 'task_report_send_email'), "&task_id=$task_id&send_report=true", "./modules/callcenter/task_entries") . '</td></tr></table><br>';

	$b = new Box(_LANG('callcenter', 'TASK_ENTRY_REPORT_GENERATED'), $mesg, '', '100%');
	$b->Draw();

	echo '</td></tr></table>';
	$_SESSION['task_report']['displayed'] = "true";
}
echo "<br />";

echo '<table cellpadding="0" cellspacing="0" width="100%"><tr><td width="100%" valign="top">';
$f = new Form($task_data, 'TASK_DISPLAY_CREATE');
$b = new Box(_LANG('callcenter', 'TASK_ENTRY_INFO_TITLE'), $f->Render(), '', '100%');
$b->Draw();//her bliver boks 1 tegnet.

echo '</td></tr><tr><td>';
$content = "";



if($doedit == 0)
{
	if ($cc->TaskIsOpen($task_id) && $userdata["tmpaccount"] == 0)
	{
		ob_start();
			$showreports = db_get("SELECT count(*) as total FROM `cc_tasks_reports` WHERE `task_id` ='$task_id' ");
			echo FunctionButton("Vis Rapporter ( ".$showreports["total"]." )", "", "javascript:toggleLayer('TASK_REPORTS');")." ";
			$docs = db_get("SELECT count(*) as total FROM `cc_tasks_documents` WHERE `task_id`='$task_id'");
			echo FunctionButton("Vedhæftet dokumenter ( ".$docs["total"]." ) ", "", "javascript:toggleLayer('TASK_DOCUMENTS');")." ";
			echo FunctionButton("Værksted status", "", "javascript:toggleLayer('tencostatus');")." ";

		$content .= ob_get_contents();
		ob_end_clean();

		$content .= " &nbsp; &nbsp; &nbsp; &nbsp; ";
		$content .= PageFunctionButton("Overfør produkt til Ego Rep", "&task_id=".$task_data['task_id']."", "./modules/callcenter/egorep").' ';
		$content .= PageFunctionButton("Tilføj RMA nummer", "&task_id=".$task_data['task_id']."", "./modules/callcenter/rma").' ';
		$content .= PageFunctionButton("Ekstern data", "&task_id=".$task_data['task_id']."", "./modules/callcenter/view_external_data").' ';
	}
}

$b = new Box("&nbsp;", $content, '', '100%');
$b->Draw();//her bliver boks nummer 2 tegnet

echo '</td></tr></table>';

if ($task_closed)
{
	$f = new Form($task_closed, 'TASK_CLOSE_DISPLAY');
	$b = new Box(_LANG('callcenter', 'TASK_CLOSED_INFO_TITLE'), $f->Render(), '', '100%');
	$b->Draw();
}

if ($doedit == 0)
{
	echo '<div id="TASK_REPORTS" style="display:none;">';
	echo "<fieldset><legend><h2>Vis rapporter:</h2></legend>";
	$list = new ListBoxEx('TASK_REPORTS');
	$list->SetOrderBy("type ASC");
	$list->SetWhere("task_id='$task_id' AND parent_id=0");
	$list->SetWidth('100%');
	$list->edit_link_href = "?p=/modules/callcenter/task_entries_reports";
	$list->delete_link_href = "/index.php?p=/modules/callcenter/task_entries_reports";
	$list->delete_link = $cc->TaskIsOpen($task_id);
	$list->Draw();

	if (!in_array("pickup", @$task_reports))
		echo PageFunctionButton(_LANG('callcenter', 'create_task_getreport'), "&act=create&type=pickup&task_id=".$task_data['task_id']."", "./modules/callcenter/task_entries_reports").' ';
	if (!in_array("workshop", @$task_reports))
		echo  PageFunctionButton(_LANG('callcenter', 'create_task_workshopreport'), "&act=create&type=workshop&task_id=".$task_data['task_id']."", "./modules/callcenter/task_entries_reports").' ';
	if (!in_array("delivery", @$task_reports))
		echo PageFunctionButton(_LANG('callcenter', 'create_task_deliveryreport'), "&act=create&type=delivery&task_id=".$task_data['task_id']."", "./modules/callcenter/task_entries_reports").' ';

	?>
	<hr color="#4A5C68">
<h3>Skadesanmeldelse:</h3>
<table class="securator_list" border="0" cellspacing="0" cellpadding="0" width="100%">
<tr class="securator_list_top_row">
	<td class="securator_list_top_center" colspan="4">Skadeanmeldelse:</td>
	<td class="securator_list_top_center">Oprettet af:</td>
	<td class="securator_list_top_center">Oprettet den:</td>
	<td class="securator_list_top_right">&nbsp;</td>
</tr>
<?php

$injuryReportExists = db_get("SELECT * FROM `injury_report` WHERE `task_id` ='".mysql_real_escape_string($task_data['task_id'])."' LIMIT 1;");

if (!$injuryReportExists["id"])
{
?>
	<tr class="securator_list_center_row">
		<td class="securator_list_center_left">&nbsp;</td>
		<td class="securator_list_center_center" colspan="5">Ingen oprettet.</td>
		<td class="securator_list_center_right">&nbsp;</td>
	</tr>
	<tr>
	<td colspan="3">
	<?php
		echo PageFunctionButton(_LANG('callcenter', 'create_task_injuryreport'), "&act=create&task_id=".$task_data['task_id']."", "./modules/callcenter/task_injury_report").'';
	?>
	</td>
	</tr>

<?php
}
else
{
	$injury_list = db_get("
		SELECT ir.*, sb.name FROM `injury_report` ir LEFT JOIN `swc_account_address_book` sb
		ON sb.account_id=0 WHERE ir.task_id = '".$task_id."' Limit 1
	");

	echo '<tr class="securator_list_center_row">';
	echo '	<td class="securator_list_center_left">&nbsp;</td>';
	echo '	<td class="securator_list_center_center" width="20"><a href="/index.php?p=./modules/callcenter/task_injury_report&act=delete&task_id='.$task_id.'"><img src="/style/icons/default/24/nd0048-24.gif" border="0"></a></td>';
	echo '	<td class="securator_list_center_center" width="20"><a href="/index.php?p=./modules/callcenter/task_injury_report&act=edit&task_id='.$task_id.'"><img src="/style/icons/default/24/ac0053-24.gif" border="0"></a></td>';
	echo '	<td class="securator_list_center_center" width="120"></td>';
	echo '	<td class="securator_list_center_center">'.$injury_list["created_by"].'</td>';
	echo '	<td class="securator_list_center_center">'.$injury_list["timedate"].'</td>';
	if(is_null($injury_list["pdf_document"]))
	{
		echo '	<td class="securator_list_center_right">Ikke oprettet</td>';
	}
	else
	{
		echo '	<td class="securator_list_center_right">';
		echo '	<a href="'.$injury_list["pdf_document"].'" target="_blank"><img src="/style/icons/default/24/adbpdf.gif" style="margin-left:5px;" border="0"></a>';
		echo '	<a href="/index.php?p=./modules/callcenter/task_injury_report&act=send&task_id='.$task_id.'"><img src="/style/icons/default/24/ei0055-24.gif" style="margin-left:5px;" border="0"></a>';
		echo '	<a href="/index.php?p=./modules/callcenter/task_injury_report&act=transfer_report&task_id='.$task_id.'"><img src="/style/icons/default/24/ni0039-24.gif" alt="Overfør til godkendelse" border="0"></a> </td>';
		echo ' </td>';
	}
	echo '</tr>';
	if(is_null($injury_list["sendto"]))
	{
		echo '<tr class="securator_list_center_row">';
		echo '	<td class="securator_list_center_left">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="2">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="3">Ikke sendt til kunden</td>';
		echo '	<td class="securator_list_center_right">&nbsp;</td>';
		echo '</tr>';
	}
	else
	{
		echo '<tr class="securator_list_center_row">';
		echo '	<td class="securator_list_center_left">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="2">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="3">Sendt til kunden den. '.$injury_list["senddate"].' på mail: <b>'.$injury_list["sendto"].'</b></td>';
		echo '	<td class="securator_list_center_right">&nbsp;</td>';
		echo '</tr>';
	}
	if(is_null($injury_list["workshopsendmail"]))
	{
		echo '<tr class="securator_list_center_row">';
		echo '	<td class="securator_list_center_left">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="2">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="3">Ikke sendt til noget værksted.</td>';
		echo '	<td class="securator_list_center_right">&nbsp;</td>';
		echo '</tr>';
	}
	else
	{
		echo '<tr class="securator_list_center_row">';
		echo '	<td class="securator_list_center_left">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="2">&nbsp;</td>';
		echo '	<td class="securator_list_center_center" colspan="3">Sendt til værksted den. '.$injury_list["workshopsenddate"].' på mail: <b>'.$injury_list["workshopsendmail"].'</b></td>';
		echo '	<td class="securator_list_center_right">&nbsp;</td>';
		echo '</tr>';
	}
}
?>
<tr class="securator_list_bottom_row">
	<td class="securator_list_bottom_left">&nbsp;</td>
	<td class="securator_list_bottom_center" colspan="5">&nbsp;</td>
	<td class="securator_list_bottom_right">&nbsp;</td>
</tr>
</table>
</fieldset>
</div>

<?php

	echo "<div id=\"TASK_DOCUMENTS\" style=\"display:none;\">";
	echo "<fieldset><legend><h2>Vedhæftet dokumenter:</h2></legend>";
		$list = new ListBoxEx('TASK_DOCUMENTS');
		$list->SetOrderBy("rec_id");
		$list->SetWhere("task_id='$task_id'");
		$list->SetWidth('100%');
		$list->Draw();
	echo '<form method="POST" enctype="multipart/form-data" action="/index.php?p=./modules/callcenter/upload_data">';
	echo '<input type="hidden" name="data" value="'.base64_encode(serialize($task_data)).'" /> ';
	echo '<input type="hidden" name="task_id" value="'.$task_id.'" /> ';
	echo '<input type="hidden" name="manualupload" value="true" /> ';
	echo '<input type="hidden" name="uploadcustomer" value="false" /> ';
	echo '<input type="file" name="file1" /> ';
	echo '<input type="submit" value="Upload fil" />';
	echo '</form>';
	echo '</fieldset>';
	echo "</div>";

}


if ($doedit == 0)
{
	$atN1S = db_get(" SELECT * FROM `number1service` WHERE task_id='".$task_id."'; ");
	$injuryReport = db_get(" SELECT id FROM `injury_report` WHERE task_id='".$task_id."'; ");
	if( (int)$atN1S["id"] == 0 && $injuryReportExists["id"] )
	{

		$dealer = db_get("
			SELECT
				pip.product_group_id, sa.groups
			FROM
				`cc_tasks` ct
			LEFT JOIN
				`products_insurance_policies` pip ON pip.product_customers_id = ct.customer_product_id
			LEFT JOIN
				`swc_account` sa ON pip.dealer_id = sa.account_id
			LEFT JOIN
				`swc_account_address_book` saab ON sa.account_id = saab.account_id
			WHERE
				ct.`task_id`='".(int)$task_id."'
			LIMIT 1;
		");

		echo '<script type="text/javascript">';
		echo 'function sendton1s() {';
		echo '	var answer = confirm("Opret ved N1S med First aid kit?");';
		echo '	if(answer) {';
		echo "		location.href='/index.php?p=./modules/callcenter/send_task_n1s&act=send_to_n1s&task_id=".$task_id."';";
		echo '	}';
		echo '}';
		echo 'function sendton1sLabel() {';
		echo '	var answer = confirm("Opret ved N1S med fragt label?");';
		echo '	if(answer) {';
		echo "		location.href='/index.php?p=./modules/callcenter/send_task_n1s&act=send_to_n1s&label=true&task_id=".$task_id."';";
		echo '	}';
		echo '}';
		if($dealer["groups"] == ",27," && ($dealer["product_group_id"] == 46 || $dealer["product_group_id"] = 47))
		{
			echo 'function sendton1sPickup() {';
			echo '	var answer = confirm("Opret ved N1S med Pickup?");';
			echo '	if(answer) {';
			echo "		location.href='/index.php?p=./modules/callcenter/send_task_n1s&act=send_to_n1s&pickup=true&task_id=".$task_id."';";
			echo '	}';
			echo '}';
		}
		echo '</script>';
		if(!is_array($task_closed) || !$cc->TaskIsOpen($task_id))
		{
			echo "<div style=\"float:right; text-align:center;\">";
			echo FunctionButton("Opret ved N1S - First Aid kit", "", "javascript:sendton1s();");
			echo FunctionButton("Opret ved N1S - Fragt Label", "", "javascript:sendton1sLabel();");
			if($dealer["groups"] == ",27," && ($dealer["product_group_id"] == 46 || $dealer["product_group_id"] = 47))
				echo FunctionButton("Opret ved N1S - Fona Pickup", "", "javascript:sendton1sPickup();");
			echo "</div>";
		}
	}

	echo "<div id=\"tencostatus\" style=\"display:none;\">";
	echo "<fieldset><legend><h2>Værksted status:</h2></legend>";
	$service_data = db_fetch_array("
		SELECT * FROM `services_receive_message` WHERE repnr='".$task_id."' ORDER BY timestamp, id;
	");
	echo '<table class="securator_list" cellspacing="0" cellpadding="0" width="100%">';
	echo '<tr class="securator_list_top_row">';
	echo '	<td class="securator_list_top_left">&nbsp;</td>';
	echo '	<td class="securator_list_top_center" nowrap>&nbsp;Status&nbsp;</td>';
	echo '	<td class="securator_list_top_center" nowrap>&nbsp;Besked&nbsp;</td>';
	echo '	<td class="securator_list_top_center" nowrap>&nbsp;Modtaget&nbsp;</td>';
	echo '	<td class="securator_list_top_right">&nbsp;</td>';
	echo '</tr>';

	if(is_array($service_data))
	{
		foreach ($service_data as $s)
		{
			echo '<tr class="securator_list_center_row">';
			echo '	<td class="securator_list_center_left">&nbsp;</td>';
			echo '	<td class="securator_list_center_center" nowrap>'.$s["status"].'</td>';
			echo '	<td class="securator_list_center_center" nowrap>'.($s["bemaerkning"] == "null " ? "" : $s["bemaerkning"] ).'</td>';
			echo '	<td class="securator_list_center_center" nowrap>'.$s["timestamp"].'</td>';
			echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
			echo '</tr>';

			if($s["offer"]) {
				if($s["account_id"] == 135715)
				{
					if($s["accepted"] == 0)
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '<br />';

						$tc = new Telecare($task_id);
						try {
							$tc_offer = $tc->WSGetOffer();
							echo '<h3>Reparationen koster: '.number_format(($tc_offer->TotalPriceExVAT)*1.25 , 2, ",", ".").' Kr</h3><br /><br />';
						} catch (Exception $e) {
							echo "Der er sket en fejl";
						}
						$tc = null;

						echo '		<form method="POST" action="'.$_SERVER["REQUEST_URI"].'">';
						echo '		<input type="hidden" name="serviceid" value="'.$s["account_id"].'" />';
						echo '		<input type="hidden" name="externalid" value="'.$task_data["external_repair_id"].'" />';
						echo '		<input type="hidden" name="offeranswer" value="'.$s["id"].'" />';
						echo '		<input type="hidden" name="returnto" value="'.urlencode($_SERVER["REQUEST_URI"]).'" />';
						echo '		<input type="submit" name="reject1" value="Tilbud afvist - Retur til kunden" />';
						echo '		<input type="submit" name="reject2" value="Tilbud afvist - Totalskadet" /> &nbsp;  &nbsp; ';
						echo '		<input type="submit" name="accept" value="Tilbud godkendt" />';
						echo '		</form><br />';
						echo '		Ved at trykke på <b>"Tilbud afvist - Totalskadet"</b>, skal du bestille ny telefon og indtaste nye IMEI til Telecare<br />';
						echo '	</td>';
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
					else
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '	<span style="font-size:14px; font-weight:bold;">';
						$timestamp = date("d-m-Y H:i", strtotime($s["accepteddate"]));

						switch ($s["accepted"])
						{
							case 1:
								echo 'Tilbud godkendt - '.$timestamp.' </span></td>';
								break;
							case 2:
								echo 'Tilbud afvist - Retur til kunden - '.$timestamp.' </span></td>';
								break;
							case 3:
								echo 'Tilbud afvist - Totalskadet - '.$timestamp.' </span></td>';
								break;
							case 4:
								echo 'Tilbud afvist - Totalskadet - '.$timestamp.' </span></td>';
								break;
							default:
								echo 'Status ukendt !!';
								break;
						}
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
				}

				if($s["account_id"] == 100667)
				{
					if($s["accepted"] == 0)
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '<br />';
						$ec = new ECWebservice($task_id);
						$repdata = $ec->getRepInfo($task_data["external_repair_id"]);
						echo '<h3>Reparationen koster: '.number_format(($repdata->outRepInfo->subtot+$repdata->outRepInfo->moms), 2, ",", ".").' Kr</h3><br /><br />';

						echo '		<form method="POST" action="'.$_SERVER["REQUEST_URI"].'">';
						echo '		<input type="hidden" name="serviceid" value="'.$s["account_id"].'" />';
						echo '		<input type="hidden" name="externalid" value="'.$task_data["external_repair_id"].'" />';
						echo '		<input type="hidden" name="offeranswer" value="'.$s["id"].'" />';
						echo '		<input type="hidden" name="returnto" value="'.urlencode($_SERVER["REQUEST_URI"]).'" />';
						echo '		<input type="submit" name="reject1" value="Tilbud afvist - Retur til kunden" />';
						echo '		<input type="submit" name="reject2" value="Tilbud afvist - Totalskadet" /> &nbsp;  &nbsp; ';
						echo '		<input type="submit" name="accept" value="Tilbud godkendt" />';
						echo '		</form>';
						echo '	</td>';
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
					else
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '	<span style="font-size:14px; font-weight:bold;">';
						switch ($s["accepted"])
						{
							case 1:
								echo 'Tilbud afvist - Retur til kunden';
								break;
							case 2:
								echo 'Tilbud afvist - Totalskadet';
								break;
							case 3:
								echo 'Tilbud godkendt';
								break;
							default:
								echo 'Status ukendt !!';
								break;
						}
						echo '	- '.date("d-m-Y H:i", strtotime($s["accepteddate"])) .' </span></td>';
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
				}
				elseif ($s["account_id"] == 59013)
				{
					if($s["accepted"] == 0)
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '		<form method="POST" action="'.$_SERVER["REQUEST_URI"].'">';
						echo '		<input type="hidden" name="serviceid" value="'.$s["account_id"].'" />';
						echo '		<input type="hidden" name="offeranswer" value="'.$s["id"].'" />';
						echo '		<input type="hidden" name="returnto" value="'.urlencode($_SERVER["REQUEST_URI"]).'" />';
						echo '		<input type="submit" name="reject1" value="Afvis og retur" />';
						echo '		<input type="submit" name="reject2" value="Afvis og skrottet" />';
						echo '		<input type="submit" name="accept" value="Godkend tilbud" />';
						echo '		</form>';
						echo '	</td>';
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
					else
					{
						echo '<tr class="securator_list_center_row">';
						echo '	<td class="securator_list_center_left">&nbsp;</td>';
						echo '	<td colspan="3" class="securator_list_center_center" style="text-align:center; padding:5px;" nowrap>';
						echo '	<span style="font-size:14px; font-weight:bold;">';
						switch ($s["accepted"])
						{
							case 1:
								echo 'Godkendt';
								break;
							case 2:
								echo 'Afvist og retur til kunden';
								break;
							case 3:
								echo 'Afvist og skrottet';
								break;
							default:
								echo 'Status ukendt !!';
								break;
						}
						echo '	- '.date("d-m-Y H:i", strtotime($s["accepteddate"])) .' </span></td>';
						echo '	<td class="securator_list_center_right" nowrap>&nbsp;</td>';
						echo '</tr>';
					}
				}
			}
			echo '<tr><td style="border-bottom: 1px solid #ccc;" colspan="5">&nbsp;</td></tr>';
		}
	}
	echo '</table>';
	echo "</fieldset></div>";

	@$list = new ListBoxEx('TASK_ENTRY');
	@$list->SetOrderBy("task_id, rec_id");
	@$list->SetWhere("task_id='$task_id' AND entry_visibility <= ".$account->GetLevel());
	@$list->SetWidth('100%');
	@$list->Draw();



	if($userdata["tmpaccount"] == 0)
	{

		if (!$task_closed && $cc->TaskIsOpen($task_id))
			echo PageFunctionButton(_LANG('system', 'create'), "&act=create&task_id=$task_id");

		echo SystemBackButton();
		echo PageFunctionButton("Omkostninger/Indtægter", "&task_id=$task_id", './modules/callcenter/task_invoice');

		echo FunctionButton("Opgavetype", "", "javascript:toggleLayer('opgavetype');")." ";


		// Henter den nuværende status på opgaven
		$task_change_data = db_get("
			SELECT
				csd.name, cs.status_id, ct.task_type
			FROM
				`cc_tasks` ct
			LEFT JOIN
				`cc_status` cs ON ct.status=cs.status_id
			LEFT JOIN
				`cc_status_data` csd ON cs.rec_id=csd.rec_id
			WHERE
				csd.language_id = 'da' AND
				ct.`task_id` = '".(int)$task_id."';
		");

		$task_change = array();
		$task_change["task_id"] = (int)$task_id;

		if(!@$_POST["task_type_id"])
		{
			$task_change["task_type"] = $task_change_data["task_type"];
		}
		else
		{
			$task_change["task_type"] = 1;
		}

		if(!@$_POST["status_new"])
		{
			$task_change["status_new"] = $task_change_data["status_id"];
		}


		if(substr(@$_POST["_form_id"], 0, 18) == "TASK_STATUS_CHANGE")
		{
			$task_change = $_POST;
			echo '<div id="opgavetype">';
		}
		else
		{
			echo '<div id="opgavetype" style="display:none;">';

		}
		$task_change["status_current"] = $task_change_data["name"];

		?>

			<fieldset>
			<legend>
			<h2>Ændring af opgavetype:</h2>
			</legend>
			<?php

			// Tjekker om der er lavet erstatningslimit på opgaven
			$compensation_exists = db_get("
				SELECT * FROM `cc_tasks_compensation` WHERE `task_id` = '".(int)$task_id."';
			");

			if(@$_POST["status_new"] == 55)
			{
				if(!$compensation_exists)
				{
					$form_name = "TASK_STATUS_CHANGE";
					$createnew = 1;
				}
				else
				{
					$task_change["limit"] = $compensation_exists["compensation_limit"];
					$task_change["product_no"] = $compensation_exists["product_number"];
					$task_change["message"] = $compensation_exists["message"];

					$form_name = "TASK_STATUS_CHANGE_ALTER";
					$createnew = 0;
				}
				$f = new Form($task_change, $form_name, $createnew);
			}
			else
			{
				$f = new Form($task_change, 'TASK_STATUS_CHANGE_CLEAN', 1);
			}

			$f->Draw();

			?>
			</fieldset>
		</div>

		<?php


		/*
		 *
		 *
		 */

		if($task_data["status"] == 95)
		{
			/* Faktura Status `status` = 'Paid' */
			$check1 = db_get("
				SELECT `status` FROM `ordersystem` WHERE `reference` LIKE '".$task_id."' AND (`status` = 'UnPaid' OR `status` = 'Collection');
			");

			/* N1S Status `status` = 'SENDT' */
			$check2 = db_get("
				SELECT `status` FROM `services_receive_message`
				WHERE `repnr` = '".$task_id."' ORDER BY `timestamp` DESC Limit 1;
			");

			/* Allrisk `allrisk` = '1' */
			/* Skadestype `damage_type` > 0 */
			$check3 = db_get("
				SELECT `allrisk`, `damage_type` FROM `cc_tasks_closed_data_invoices`
				WHERE `task_id` = '".$task_id."' AND `invoice_type` = 'EXPENSE';
			");

			echo '<center>';
			echo '<table width="450"><tr><td>';
			$total = 0;
			if($check1 == null)
			{
				$total++;
				echo '<font color="#336600">Fakturaen er betalt.</font><br />';
			}
			else
				echo '<font color="#ff0000">Fakturaen er ikke betalt.</font><br />';

			if($check2["status"] == "SENDT")
			{
				$total++;
				echo '<font color="#336600">Produktet er sendt fra N1S.</font><br />';
			}
			else
				echo '<font color="#ff0000">Produktet er ikke sendt fra N1S.</font><br />';

			if($check3["allrisk"] == "1")
			{
				$total++;
				echo '<font color="#336600">Allrisk er valgt på omkostninger.</font><br />';
			}
			else
				echo '<font color="#ff0000">Allriske er ikke valgt på omkostninger.</font><br />';

			if($check3["damage_type"] > 0)
			{
				$total++;
				echo '<font color="#336600">Skadestypen er valgt på omkostninger.</font><br />';
			}
			else
				echo '<font color="#ff0000">Skadestypen er ikke valgt på omkostninger.</font><br />';

			echo "<br />";
			if($total == 4)
			{
				$formArray = array('task_id' => $task_id);
				$f = new Form($formArray, 'TASK_QUICK_CLOSE', 1);
				$b = new Box("Hurtig lukning af opgaven", $f->Render(), '', '100%');
				$b->Draw();

			}
			echo '</td></tr>';
			echo '</table>';
			echo '</center>';
			echo '<br /><br /><br />';
		}
	}
}


if ($doedit == 1 && $userdata["tmpaccount"] == 0)
{

	if ($task_id != '' && $cc_entry==NULL)
		$cc_entry = db_get("SELECT * FROM cc_tasks_entry WHERE task_id=$task_id AND rec_id=$rec_id");

	// HTML replacer
	@$cc_entry["note"] = htmlremover(@$cc_entry["note"]);

	if ($docreate)
	{
		$f = new Form($cc_entry, 'TASK_ENTRY', $docreate);
	}
	else
	{
		$f = new Form($cc_entry, 'TASK_ENTRY_EDIT', $docreate);
	}
	$f->AddButton(SystemBackButton());

	$title = _LANG('callcenter', 'TASK_ENTRY_EDIT');
	if ($docreate)
		$title = _LANG('callcenter', 'TASK_ENTRY_CREATE');
	if ($task_closed || !$cc->TaskIsOpen($task_id))
	{
		$f->setup['button_update'] = 0;
	}

	$b = new Box($title, $f->Render(), '', '100%');
	$b->Draw();
	//Opret Opgave

	echo '</td></tr></table>';
}


exit();

?>
