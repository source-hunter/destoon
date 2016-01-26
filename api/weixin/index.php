<?php
require '../../common.inc.php';
require DT_ROOT.'/api/weixin/init.inc.php';
if($wx->signature()) {
	if($HTTP_RAW_POST_DATA) {
		$x = simplexml_load_string($HTTP_RAW_POST_DATA, 'SimpleXMLElement', LIBXML_NOCDATA);
		$ToUserName = $x->ToUserName;
		$FromUserName = $x->FromUserName;
		$CreateTime = $x->CreateTime;
		$MsgType = $x->MsgType;
		$credit_add = 0;
		$post = array();
		$post['openid'] = $FromUserName;
		$post['addtime'] = $DT_TIME;//$CreateTime;
		$post['type'] = $MsgType;
		$post['misc'] = array();
		if($MsgType == 'event') {//�¼�
			$Event = $x->Event;
			$post['type'] = $Event;
			$post['event'] = 1;
			$EventKey = $x->EventKey;			
			switch($Event) {
				case 'CLICK'://����˵���ȡ��Ϣʱ���¼�����
					switch($EventKey) {
						case 'V_member'://�󶨻�Ա
							$post['content'] = $EventKey;
							//�ظ�ͼ����Ϣ
							$misc = $tags = array();
							$tags['title'] = '��Ա����';
							$tags['description'] = $WX['bind'];
							$tags['picurl'] = DT_PATH.'api/weixin/image/top_bind.jpg';
							$tags['url'] = $EXT['mobile_url'].'weixin.php?action=member&auth='.encrypt("$FromUserName");
							$misc[] = $tags;						
							$wx->response($FromUserName, $ToUserName, 'news', '', $misc);
						break;
						default:
							if(substr($EventKey, 0, 5) == 'V_mid') {
								$mid = intval(substr($EventKey, 5));
								$mod = $MODULE[$mid]['module'];
								if(in_array($mod, array('quote', 'exhibit', 'article', 'job', 'know', 'brand', 'group', 'video', 'photo'))) {
									$post['content'] = $EventKey;
									$misc = array();
									$result = $db->query("SELECT itemid,title,thumb,level FROM ".get_table($mid)." WHERE status=3 AND thumb<>'' AND level=2 ORDER BY addtime DESC LIMIT 1");
									while($r = $db->fetch_array($result)) {
										$tags = array();
										$tags['title'] = $r['title'];
										$tags['description'] = '';
										$tags['picurl'] = $r['thumb'];
										$tags['url'] = $EXT['mobile_url'].'index.php?moduleid='.$mid.'&itemid='.$r['itemid'];
										$misc[] = $tags;
									}
									if($misc) {
										$result = $db->query("SELECT itemid,title,thumb,level FROM ".get_table($mid)." WHERE status=3 AND thumb<>'' AND level=1 ORDER BY addtime DESC LIMIT 3");
										while($r = $db->fetch_array($result)) {
											$tags = array();
											$tags['title'] = $r['title'];
											$tags['description'] = '';
											$tags['picurl'] = $r['thumb'];
											$tags['url'] = $EXT['mobile_url'].'index.php?moduleid='.$mid.'&itemid='.$r['itemid'];
											$misc[] = $tags;
										}					
										$wx->response($FromUserName, $ToUserName, 'news', '', $misc);
									}
								} else if(in_array($mod, array('sell', 'buy', 'info', 'mall'))) {
									$post['content'] = $EventKey;
									$misc = array();
									$result = $db->query("SELECT itemid,title,thumb,level FROM ".get_table($mid)." WHERE status=3 AND thumb<>'' AND level>0 ORDER BY addtime DESC LIMIT 4");
									while($r = $db->fetch_array($result)) {
										$tags = array();
										$tags['title'] = $r['title'];
										$tags['description'] = '';
										$tags['picurl'] = str_replace('.thumb.', '.middle.', $r['thumb']);
										$tags['url'] = $EXT['mobile_url'].'index.php?moduleid='.$mid.'&itemid='.$r['itemid'];
										$misc[] = $tags;
									}					
									$wx->response($FromUserName, $ToUserName, 'news', '', $misc);
								}
							}
						break;
					}
				break;
				case 'subscribe'://����
					if(strlen($EventKey) > 0) {//ɨ���ά���ע
						$post['content'] = $EventKey;
						$post['misc']['Ticket'] = "$x->Ticket";
					} else {//��ͨ��ע
						$post['content'] = '';
					}
					$user = weixin_user($FromUserName);
					$info = $wx->get_user($FromUserName);
					$sql = "subscribe=1,addtime=".$info['subscribe_time'].",edittime=$DT_TIME,visittime=$DT_TIME";
					foreach(array('nickname', 'sex', 'city', 'province', 'country', 'language', 'headimgurl') as $v) {
						if(isset($info[$v])) $sql .= ",".$v."='".addslashes($info[$v])."'";
					}
					if($user) {
						$db->query("UPDATE {$DT_PRE}weixin_user SET $sql WHERE openid='$FromUserName'");
					} else {
						$sql .= ",openid='$FromUserName'";
						$db->query("INSERT INTO {$DT_PRE}weixin_user SET $sql");
					}
					if(strpos($post['content'], 'qrscene_') !== false) {
						$sid = intval(substr($post['content'], 8));
						$B = $db->get_one("SELECT * FROM {$DT_PRE}weixin_bind WHERE sid='$sid'");
						if($B) {
							if($DT_TIME - $B['addtime'] < 1800 && check_name($B['username'])) weixin_bind($FromUserName, $B['username']);
							$db->query("DELETE FROM {$DT_PRE}weixin_bind WHERE sid='$sid'");
						}
					}
					//�ظ���ӭ��Ϣ
					$wx->response($FromUserName, $ToUserName, 'text', $WX['welcome']);
				break;
				case 'unsubscribe'://ȡ������
					$post['content'] = '';
					$db->query("UPDATE {$DT_PRE}weixin_user SET subscribe=0,username='',edittime=$DT_TIME WHERE openid='$FromUserName'");
				break;
				case 'SCAN'://ɨ���ά��[�ѹ�ע]
					$post['content'] = $EventKey;
					$post['misc']['Ticket'] = "$x->Ticket";
					if($EventKey == '99999') $credit_add = 1;
				break;
				case 'LOCATION'://�ϱ�����λ���¼�
					$post['content'] = '';
					$post['misc']['Latitude'] = "$x->Latitude";
					$post['misc']['Longitude'] = "$x->Longitude";
					$post['misc']['Precision'] = "$x->Precision";
				break;
				case 'VIEW'://����˵���ת����ʱ���¼�����
					$post['content'] = $EventKey;
				break;
				default:
				break;
			}
		} else {//��Ϣ
			switch($MsgType) {
				case 'text'://�ı���Ϣ
					$Content = convert("$x->Content", 'UTF-8', DT_CHARSET);
					$post['content'] = $Content;
					if($Content == 'ǩ��') $credit_add = 1;
					//�Զ��ظ�
				break;
				case 'image'://ͼƬ��Ϣ
					$post['content'] = $x->PicUrl;
					$post['misc']['MediaId'] = "$x->MediaId";
				break;
				case 'voice'://������Ϣ
					$post['content'] = '';
					$post['misc']['Format'] = "$x->Format";
					$post['misc']['MediaId'] = "$x->MediaId";
				break;
				case 'video'://��Ƶ��Ϣ
					$post['content'] = '';
					$post['misc']['ThumbMediaId'] = "$x->ThumbMediaId";
					$post['misc']['MediaId'] = "$x->MediaId";
				break;
				case 'location'://����λ����Ϣ
					$post['content'] = convert("$x->Label", 'UTF-8', DT_CHARSET);
					$post['misc']['Location_X'] = "$x->Location_X";
					$post['misc']['Location_Y'] = "$x->Location_Y";
					$post['misc']['Scale'] = "$x->Scale";
				break;
				case 'link'://������Ϣ
					$post['content'] = $x->Url;
					$post['misc']['Title'] = convert("$x->Title", 'UTF-8', DT_CHARSET);
					$post['misc']['Description'] = convert("$x->Description", 'UTF-8', DT_CHARSET);
				break;
				default:
				break;
			}
		}
		if(isset($post['content'])) {
			$post['misc'] = $post['misc'] ? serialize($post['misc']) : '';
			$post = daddslashes($post);
			$sql = '';
			foreach($post as $k=>$v) {
				$sql .= ",$k='$v'";
			}
			$db->query("INSERT INTO {$DT_PRE}weixin_chat SET ".substr($sql, 1));
		}
		if($credit_add && $WX['credit']) {//ǩ���ͻ���
			$credit = intval($WX['credit']);
			$user = weixin_user($FromUserName);
			if($user['credittime'] < 1) $user['credittime'] = 1;
			$msg = '��ӭ������������ǩ���������ʹ����������';
			if($credit && $user && $user['username'] && timetodate($DT_TIME, 3) != timetodate($user['credittime'], 3)) {
				require_once DT_ROOT.'/include/module.func.php';
				credit_add($user['username'], $credit);
				credit_record($user['username'], $credit, 'system', '΢��ǩ��');
				$db->query("UPDATE {$DT_PRE}weixin_user SET credittime=$DT_TIME WHERE itemid=$user[itemid]");
				$msg = 'ǩ���ɹ�����������'.$credit.$DT['credit_name'];
			}
			$wx->response($FromUserName, $ToUserName, 'text', $msg);
		}
		$db->query("UPDATE {$DT_PRE}weixin_user SET visittime=$DT_TIME WHERE openid='$FromUserName'");
	} else {
		echo $_GET["echostr"];
	}
} else {
	echo DT_DEBUG ? 'Working...' : '<meta http-equiv="refresh" content="0;url=../">';
}
?>