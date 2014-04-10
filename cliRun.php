<?php

header("Content-type: text/html; charset=utf-8");
date_default_timezone_set('PRC');
require("./pve2_api.class.php");
require_once('./PHPMailer_5.2.4/class.phpmailer.php');

function xiaoshu($nums) {
    return number_format($nums, 2, '.', '');
}

//$mujiBili = 0.7;
$bili = 0.8;
$bootTime = 120;
$cpuNums = 1.5;
$ci = 0;
$serverListArr = array('8.8.8.8'=>'0.8', '127.0.0.1'=>'0.7', '2.2.2.2'=>'0.9');
$alertMsgArr = array('alertnums'=>0);
foreach ($serverListArr as $serverIP=>$mujiBili) {

    $ip = $serverIP;


    $pve2 = new PVE2_API($ip, "root", "pam", "password");



    $mail = new PHPMailer(true); // the true param means it will throw exceptions on errors, which we need to catch

    $mail->IsSMTP(); // telling the class to use SMTP

    $mail->Host = "smtp.126.com"; // SMTP server
    $mail->SMTPDebug = 0;                     // enables SMTP debug information (for testing)
    $mail->SMTPAuth = true;                  // enable SMTP authentication
    $mail->Host = "smtp.126.com"; // sets the SMTP server
    $mail->Port = 25;                    // set the SMTP port for the GMAIL server
    $mail->Username = "wwww"; // SMTP account username
    $mail->Password = "wwww";        // SMTP account password
    $mail->AddAddress('ewwwric@pingcoo.com', 'ericwang');
    
    $mail->SetFrom('pingcowwwotech@www.com', '宾谷小秘书');
    $mail->AddReplyTo('pingcowwwotech@www.com', '宾谷小秘书');
    $mail->Subject = '宾谷监控宝';


    if ($pve2->constructor_success()) {
        /* Optional - enable debugging. It print()'s any results currently */
        // $pve2->set_debug(true);
        $alertMujiMsgStr = '';
        if ($pve2->login()) {
            foreach ($pve2->get_node_list() as $node_name) {
                //print_r($pve2->get("/nodes/".$node_name."/status"));
                $mujiArr = $pve2->get("/nodes/" . $node_name . "/status");
                //print_r($mujiArr);
                if (is_array($mujiArr) && !empty($mujiArr)) {
                    //母鸡内存：memory
                    if (($mujiArr['memory']['used'] / $mujiArr['memory']['total']) > $mujiBili) {
                        $alertMujiMsgStr.="{$node_name}[$ip]母鸡内存占比达到" . ($mujiBili * 100) . '%，' . xiaoshu($mujiArr['memory']['used'] / (1024 * 1024 * 1024)) . 'G。 ';
                    }
                    //母鸡磁盘：
                    if (($mujiArr['rootfs']['used'] / $mujiArr['rootfs']['total']) > $mujiBili) {
                        $alertMujiMsgStr.="{$node_name}[$ip]母鸡系统磁盘占比达到" . ($mujiBili * 100) . '%，' . xiaoshu($mujiArr['rootfs']['used'] / (1024 * 1024 * 1024)) . 'G。 ';
                    }
                    $diskArr = $pve2->get("/nodes/{$node_name}/storage/local/status");
                    if (is_array($diskArr) && !empty($diskArr)) {
                        //print_r($diskArr);
                        if (($diskArr['used'] / $diskArr['total']) > $mujiBili) {
                            $alertMujiMsgStr.="{$node_name}[$ip]母鸡数据磁盘占比达到" . ($mujiBili * 100) . '%，' . xiaoshu($diskArr['used'] / (1024 * 1024 * 1024)) . 'G。 ';
                        }
                    }

                    if ($alertMujiMsgStr != "") {
                        $alertMsgArr['母鸡数据告警:' . 'https://' . $ip . ':8006/#v1:0:=node%2F' . $node_name . ':4::::::'] = $alertMujiMsgStr;
                    }
                }
            }
            //获取要关注的投放机集群
            $poolsArr = $pve2->get('pools/www');


            if (is_array($poolsArr) && isset($poolsArr['members'])) {
                $poolName = $poolsArr['comment'];
                $poolNodeArr = $poolsArr['members'];
                foreach ($poolNodeArr as $nodeArr) {

                    /* 如果下面的参数超过80%,则发邮件提醒 */
                    //内存比例：
                    $alertMsgStr = "";
                    if (($nodeArr['mem'] / $nodeArr['maxmem']) > $bili) {
                        //todo mail
                        $alertMsgStr.="内存占比达到" . ($bili * 100) . '%，' . xiaoshu($nodeArr['mem'] / (1024 * 1024 * 1024)) . 'G。 ';
                    }

                    //硬盘比例:
                    $txtStr = "";
                    if ($nodeArr['type'] == 'qemu') {
                        $nodeArr['disk'] = $nodeArr['diskwrite'];
                        $txtStr = '写入';
                    }
                    if (($nodeArr['disk'] / $nodeArr['maxdisk']) > $bili) {
                        //todo mail
                        $alertMsgStr.="硬盘{$txtStr}占比达到" . ($bili * 100) . '%，' . xiaoshu($nodeArr['disk'] / (1000 * 1000 * 1000)) . 'G。 ';
                    }
                    //启动时间:
                    if ($nodeArr['uptime'] < $bootTime) {
                        //机器在$bootTime重启，发邮件通知
                        $alertMsgStr.="机器在" . $nodeArr['uptime'] . '秒前被重启，' . '。 ';
                    }
                    //cpu 比例:
                    if ($nodeArr['cpu'] > $cpuNums) {
                        //当CPU超过1.5的时候报警
                        $alertMsgStr.='CPU占用率过高' . $nodeArr['cpu'] . '。' . $nodeArr['cpu'] . '。 ';
                    }

                    //status
                    if ($nodeArr['status'] != 'running' && $ip != '180.153.87.20') {
                        //机器非运行状态。
                        $alertMsgStr.='机器不在运行状态.' . '！ ';
                    }


                    if ($alertMsgStr != '') {
                        $ci++;
                        $alertMsgArr['  小鸡:https://' . $ip . ':8006/#v1:0:=' . $nodeArr['id'] . ':4::::::'] = $alertMsgStr;
                    }
                    $alertMsgArr['alertnums'] = $ci;
                }
            }
        } else {
            print("Login to Proxmox Host failed.\n");
            continue;
        }
    } else {
        print("Could not create PVE2_API object.\n");
        continue;
    }
}

if (isset($alertMsgArr['alertnums']) && $alertMsgArr['alertnums'] > 0) {
    $mail->Body = '机器有点小麻烦，快来看看：' . print_r($alertMsgArr, ture);

    try {
        $mail->Send();
        echo $mail->Body;
        echo "Message Sent OK</p>\n";
    } catch (phpmailerException $e) {
        echo $e->errorMessage(); //Pretty error messages from PHPMailer
    } catch (Exception $e) {
        echo $e->getMessage(); //Boring error messages from anything else!
    }
}