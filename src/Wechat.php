<?php
namespace usualtool\Wechat;
use usualtool\Wechat\Http;
/**
 *公众号
 *小程序部分可用：如客服消息
*/
class Wechat{
    /**
     * @param string $appid 公众号APPID
     * @param string $appsecret 密钥
     * @param string $token 消息解析密钥
     */
    public function __construct($appid,$appsecret,$token=''){
        $this->appline="https://api.weixin.qq.com";
        $this->appid=$appid;
        $this->appsecret=$appsecret;
        $this->token=$token;
    }
    /**
     * 读取有效的AccessToken
     * @return string 有效期90分钟
     */
    public function GetToken(){
        $file = file_get_contents(UTF_ROOT."/log/wechat.".$this->appid.".token.json",true);
        $result = json_decode($file,true);
        if($result==null || !isset($result['expires']) || time()>$result['expires']):
            $data = array();
            $data['access_token'] = $this->GetNewToken();
            $data['expires']=time()+5400;
            $jsonStr =  json_encode($data);
            file_put_contents(UTF_ROOT."/log/wechat.".$this->appid.".token.json",$jsonStr);
            return $data['access_token'];
        else:
            return $result['access_token'];
        endif;
    }
    /**
     * 获取新AccessToken
     * @return string
     */
    public function GetNewToken(){
        $url = "{$this->appline}/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->appsecret}";
        $access_token_Arr =  Http::GetData($url);
        return $access_token_Arr['access_token'];
    }
    /**
     * 获取消息模板
     * @return array
     */
    public function GetTemp(){
        $url = "{$this->appline}/cgi-bin/template/get_all_private_template?access_token={$this->GetToken()}";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 删除消息模板
     * @param string $id 模板ID
     * @return array
     */
    public function DelTemp($id){
        $url = "{$this->appline}/cgi-bin/template/del_private_template?access_token={$this->GetToken()}";
        $data=json_encode(array("template_id"=>$id));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 向指定用户发送模板消息
     * @param string $tempid 模板ID
     * @param string $openid 用户OPENID,多个逗号分隔
     * @param json/string $data 模板内容
     * @param string $link 跳转链接
     * @return array
     */
    public function SendTemp($tempid,$openid,$data,$link=''){
        $url="{$this->appline}/cgi-bin/message/template/send?access_token={$this->GetToken()}";
        $arr=explode(",",$openid);
        for($i=0;$i<count($arr);$i++){
            $senddata = '{
                "touser":"'.$arr[$i].'",
                "template_id":"'.$tempid.'",
                "url":"'.$link.'",
                "data":'.$data.'
            }';
            Http::PostData($url,$senddata);
        }
    }
    /**
     * 向所有粉丝发送模板消息（fsockopen方法）（较快）
     * @param string $tempid 模板ID
     * @param json/string $data 模板内容
     * @param int $tag 是否按粉丝标签发送，默认为0所有粉丝，大于0标签ID下粉丝
     * @param string $link 跳转链接
     * @return array
     */
    public function SendTempToAll($tempid,$data,$tag='0',$link=''){
        $appline=$this->appline;
        $token=$this->GetToken();
        if($tag==0):
            $userList=$this->GetUserList()['data']['openid'];
        else:
            $userList=$this->GetTagUser($tag)['data']['openid'];
        endif;
        foreach($userList as $val){
            $senddata = '{
                "touser":"'.$val.'",
                "template_id":"'.$tempid.'",
                "url":"'.$link.'",
                "data":'.$data.'
            }';
            $fp = fsockopen("ssl://".$appline,443,$error,$errstr,1);
            $http = "POST /cgi-bin/message/template/send?access_token=".$token." HTTP/1.1\r\nHost: ".$appline."\r\nContent-type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($senddata)."\r\nConnection:close\r\n\r\n".$senddata."\r\n\r\n";
            fwrite($fp,$http);
            fclose($fp);
        }
    }
    /**
     * 向指定用户发送客服消息
     * @param string $openid 客户OPENID
     * @param string $text 发送内容
     * @return array
     */
    public function SendMsg($openid,$text){
        $jsonurl=UTF_ROOT."/log/wechat/".$this->appid."/".$openid.".json";
        //存放事件日志
        //重复消息过滤
        $msg=array("type"=>$type,"openid"=>"admin","content"=>$text,"time"=>time());
        if(file_exists($jsonurl)){
            $jsonstr=file_get_contents($jsonurl);
            $jsondata = json_decode($jsonstr,true);
            array_unshift($jsondata,$msg);
        }else{
            $jsondata=array();
            $jsondata[]=$msg;
        }
        $jsonstrs=json_encode($jsondata,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        file_put_contents($jsonurl,$jsonstrs);
        $url = "{$this->appline}/cgi-bin/message/custom/send?access_token={$this->GetToken()}";
        $data = '{
            "touser":"'.$openid.'",
            "msgtype":"text",
            "text":{
                "content":"'.$text.'"
            }
        }';
        Http::PostData($url,$data);
    }
    /**
     * 向部分粉丝（48H内互动有效）发送图文消息（较慢）
     * @param string $title 消息标题
     * @param string $text 纯文本内容（消息截取部分）
     * @param string $link 消息链接
     * @param string $photo 消息封面图
     * @return array
     */
    public function SendMsgToAll($title,$text,$link,$photo){
        $userList=$this->GetUserList()['data']['openid'];
        $url = "{$this->appline}/cgi-bin/message/custom/send?access_token={$this->GetToken()}";
        foreach($userList as $val){
            $data = '{
                "touser":"'.$val.'",
                "msgtype":"link",
                "link":{
                    "title":"'.$title.'",
                    "description":"'.$text.'",
                    "url":"'.$link.'",
                    "thumb_url": "'.$photo.'"
                }
            }';
            Http::PostData($url,$data);
        }
    }
    /**
     * 创建公众号菜单
     * @param json/string $data 菜单数据
     * @return string
     */
    public function CreatMenu($data){
        $url = "{$this->appline}/cgi-bin/menu/create?access_token={$this->GetToken()}";
        $res = Http::PostData($url,$data);
        $rerult=$res['errmsg'];
        return $rerult;
    }
    /**
     * 清除公众号菜单
     * @return string
     */
    public function DelMenu(){
        $url = "{$this->appline}/cgi-bin/menu/delete?access_token={$this->GetToken()}";
        $res = Http::GetData($url);
        $rerult = $res['errmsg'];
        return $rerult;
    }
    /**
     * 接收公众号事件
     * 可连接UT框架数据UTData处理数据
     * @return string
     */
    public function ResponseMsg(){
        //接收事件内容
        $data = file_get_contents("php://input");
        if(!empty($data)){
            libxml_disable_entity_loader(true);
            $obj = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
            $type = trim($obj->MsgType);
            $openid=$obj->FromUserName;
            //判断是否是扫码事件
            if($obj->Ticket){
                $ticket=$obj->Ticket;
                $eventkey=$obj->EventKey;
                Http::MakeDir(UTF_ROOT."/log/wechat/".$this->appid."/ticket/");
                $jsonurl=UTF_ROOT."/log/wechat/".$this->appid."/ticket/".$ticket.".json";
                $data=json_encode(array("openid"=>$openid,"eventkey"=>$eventkey));
                file_put_contents($jsonurl,$data);
            }else{
                //解析事件内容
                if($type=="event"):
                    if($obj->Event=="subscribe"):
                        $content="已关注公众号。";
                    endif;
                elseif($type=="text"):
                    $content=$obj->Content;
                elseif($type=="image"):
                    $content=$obj->PicUrl;
                elseif($type=="voice"):
                    $content=$obj->MediaId;
                    $this->GetLocal("voice",$content);
                elseif($type=="video"):
                    $content=$obj->MediaId;
                    $this->GetLocal("video",$content);
                elseif($type=="location"):
                    $content="纬度：".$obj->Location_X."，经度：".$obj->Location_Y."，缩放级别：".$obj->Scale."，位置：".$obj->Label."";
                elseif($type=="link"):
                    $content="<a href='".$obj->Url."'><b>".$obj->Title."</b><br>".$obj->Description."</a>";
                endif;
                $time=$obj->CreateTime;
                Http::MakeDir(UTF_ROOT."/log/wechat/".$this->appid."/");
                $jsonurl=UTF_ROOT."/log/wechat/".$this->appid."/".$openid.".json";
                //存放事件日志
                //重复消息过滤
                if(!empty($content) && $content!="null"){
                    $msg=array("type"=>$type,"openid"=>$openid,"content"=>$content,"time"=>$time);
                    if(file_exists($jsonurl)){
                        $jsonstr=file_get_contents($jsonurl);
                        $jsondata = json_decode($jsonstr,true);
                        array_unshift($jsondata,$msg);
                    }else{
                        $jsondata=array();
                        $jsondata[]=$msg;
                    }
                    $jsonstrs=json_encode($jsondata,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                    file_put_contents($jsonurl,$jsonstrs);
                }
                //事件自动回复
                if($type=="event"){
                    if($obj->Event=="subscribe"){
                        $receive="感谢您的关注，授权并绑定账号可以解锁更多功能。";
                        $result=$this->ReceiveMsg($obj,$receive);
                    }else{
                        echo"success";
                    }
                }else{
                    echo"success";
                }
            }
        }else{
            exit("未接收到有效的数据");
        }
    }
    /**
     * 回复公众号事件
     * @return string
     */
    public function ReceiveMsg($object,$receive){
        $result = $this->TransmitText($object,$receive);
        echo $result;
        exit();
    }
    /**
     * 数据整合排序
     * @param array $object 接收数据
     * @return string $content 发送数据
     * @return string
     */
    public function TransmitText($object, $content){
        $textTpl = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[text]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        </xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }
    /**
     * 获取所有粉丝
     * @return array
     */
    public function GetUserList(){
        $url = "{$this->appline}/cgi-bin/user/get?access_token={$this->GetToken()}";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 获取粉丝信息（仅关注信息）
     * @param string $openid OPENID
     * @return array
     */
    public function GetUser($openid){
        $url = "{$this->appline}/cgi-bin/user/info?access_token={$this->GetToken()}&openid={$openid}&lang=zh_CN";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 获取黑粉
     * @param string $begin 第一个拉取的OPENID
     * @return array
     */
    public function UserBlack($begin=''){
        $url = "{$this->appline}/cgi-bin/tags/members/getblacklist?access_token={$this->GetToken()}";
        $data='{"begin_openid":"'.$begin.'"}';
        $res = Http::PostData($url,$data);
        $resx=json_encode($res);
        if(strpos($resx,'errmsg')!==false){
            return $res["errmsg"];
        }else{
            return $res['data']['openid'];
        }
    }
    /**
     * 拉黑粉丝
     * @param string $openid OPENID
     * @return string
     */
    public function UserSetBlack($openid){
        $url = "{$this->appline}/cgi-bin/tags/members/batchblacklist?access_token={$this->GetToken()}";
        $data='{
        "openid_list":["'.$openid.'"]
        }';
        $res = Http::PostData($url,$data);
        return $res["errmsg"];
    }
    /**
     * 粉丝取消拉黑状态
     * @param string $openid OPENID
     * @return string
     */
    public function UserUnBlack($openid){
        $url = "{$this->appline}/cgi-bin/tags/members/batchunblacklist?access_token={$this->GetToken()}";
        $data='{
            "openid_list":["'.$openid.'"]
        }';
        $res = Http::PostData($url,$data);
        return $res["errmsg"];
    }
    /**
     * 获取标签
     * @return array
     */
    public function GetTag(){
        $url = "{$this->appline}/cgi-bin/tags/get?access_token={$this->GetToken()}";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 创建标签
     * @param string $name 标签名称
     * @return array
     */
    public function CreatTag($name){
        $url = "{$this->appline}/cgi-bin/tags/create?access_token={$this->GetToken()}";
        $data='{
            "tag":{
                "name":"'.$name.'"
            }
        }';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 更新标签
     * @param int $id 标签ID
     * @param string $name 标签名称
     * @return array
     */
    public function UpdateTag($id,$name){
        $url = "{$this->appline}/cgi-bin/tags/update?access_token={$this->GetToken()}";
        $data='{
            "tag":{
                "id":"'.$id.'",
                "name":"'.$name.'"
            }
        }';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 删除标签
     * @param int $id 标签ID
     * @return array
     */
    public function DelTag($id){
        $url = "{$this->appline}/cgi-bin/tags/delete?access_token={$this->GetToken()}";
        $data='{
            "tag":{
                "id":"'.$id.'"
            }
        }';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 获取标签下的粉丝
     * @param int $id 标签ID
     * @param string $begin 第一个拉取的OPENID
     * @return array
     */
    public function GetTagUser($id,$begin=''){
        $url = "{$this->appline}/cgi-bin/user/tag/get?access_token={$this->GetToken()}";
        $data='{"tagid":"'.$id.'","next_openid":"'.$begin.'"}';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 获取指定用户的标签
     * @param int $openid 用户OPENID
     * @return array
     */
    public function GetUserTag($openid){
        $url = "{$this->appline}/cgi-bin/tags/getidlist?access_token={$this->GetToken()}";
        $data='{"openid":"'.$openid.'"}';
        $res = Http::PostData($url,$data);
        $tag=$res["tagid_list"];
        return $tag;
    }
    /**
     * 批量为用户打上标签
     * @param int $id 标签ID
     * @param array OPENID数组
     * @return array
     */
    public function BindTag($id,$openid){
        $url = "{$this->appline}/cgi-bin/tags/members/batchtagging?access_token={$this->GetToken()}";
        $data='{
            "openid_list":'.json_encode($openid).',
            "tagid" : '.$id.'
        }';
        $res = Http::PostData($url,$data);
        return $tag;
    }
    /**
     * 批量取消用户标签
     * @param int $id 标签ID
     * @param array OPENID数组
     * @return array
     */
    public function UnbindTag($id,$openid){
        $url = "{$this->appline}/cgi-bin/tags/members/batchuntagging?access_token={$this->GetToken()}";
        $data='{
            "openid_list":'.json_encode($openid).',
            "tagid" : '.$id.'
        }';
        $res = Http::PostData($url,$data);
        return $tag;
    }
    /**
     * 获取音频素材
     * @param string $voiceid 音频素材ID
     * @return string
     */
    public function GetVoice($voiceid){
        $url = "{$this->appline}/cgi-bin/media/voice/queryrecoresultfortext?access_token={$this->GetToken()}&voice_id={$voiceid}&lang=zh_CN";
        $res = Http::PostData($url);
        $result =$res['result'];
        return $result;
    }
    /**
     * 将音/视频保存到本地
     * @param string $type 类型
     * @param string $mediaid 音/视频素材ID
     * @return
     */
    public function GetLocal($type,$mediaid){
        $mediaurl = "{$this->appline}/cgi-bin/media/get?access_token={$this->GetToken()}&media_id={$mediaid}";
        $res = file_get_contents($mediaurl);
        Http::MakeDir(APP_ROOT."/assets/upload/wechat/");
        if($type=="voice"){
            $respath = APP_ROOT ."/assets/upload/wechat/".$mediaid.".amr";
        }elseif($type=="video"){
            $respath = APP_ROOT ."/assets/upload/wechat/".$mediaid.".mp4";
        }
        file_put_contents($respath,$res);
    }
    /**
     * 获取聊天记录
     * @param string $stime 开始时间
     * @param string $etime 结束时间
     * @param string $msgid 开始消息ID
     * @param string $limit 获取数量
     * @return array
     */
    public function GetMsg($stime,$etime,$msgid,$limit){
        $url = "{$this->appline}/customservice/msgrecord/getmsglist?access_token={$this->GetToken()}";
        $data = '{
            "starttime":{$stime},
            "endtime":{$etime},
            "msgid":{$msgid},
            "number":{$limit} 
        }';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 获取已发布文章列表
     * @param int $offset 开始位置 默认0
     * @param int $count 数量 1-20
     * @param int $istext 0返回content 1不返回content
     * @return array
     */
    public function GetPublish($offset='0',$count='20',$istext='1'){
        $url = "{$this->appline}/cgi-bin/freepublish/batchget?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "offset"=>$offset,
					  "count"=>$count,
					  "no_content"=>$istext
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 获取已发布文章详情
     * @param string $aid 文章ID
     * @return array
     */
    public function GetArticle($aid){
        $url = "{$this->appline}/cgi-bin/freepublish/getarticle?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "article_id"=>$aid
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 删除已发布文章
     * @param string $aid 文章ID
     * @return array
     */
    public function DelPublish($aid){
        $url = "{$this->appline}/cgi-bin/freepublish/delete?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "article_id"=>$aid
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
		 * 解析加密参数（授权获取手机号）
     * @param array $sessionKey
     * @param array $encryptedData 加密数据
     * @param array $iv  
     * @param array $data  返回解析数据
     * @return blob
     */
    public function DecryptData($sessionKey,$encryptedData,$iv,&$data){
				$aesKey=base64_decode($sessionKey);
				$aesIV=base64_decode($iv);
				$aesCipher=base64_decode($encryptedData);
				$result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
				$dataObj=json_decode($result);
				if($dataObj==NULL || empty(dataObj)){
					return "-41003";
				}
				if($dataObj->watermark->appid!=$this->appid){
					return "-41003";
				}
				$data = $result;
				return 0;
	  }
    /**
     * 验证签名
     * @return bool
     */
    public function CheckSignature(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = $this->token;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 创建带参数的二维码（1800秒有效期）
     * @param string $eventkey 事件标记
     * @return array
     */
    public function CreatQrcode($eventkey){
        $url = "{$this->appline}/cgi-bin/qrcode/create?access_token={$this->GetToken()}";
        $data='{
            "expire_seconds": 1800,
            "action_name": "QR_STR_SCENE",
            "action_info": {
                "scene": {
                    "scene_str": "'.$eventkey.'"
                }
            }
        }';
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 微信被动通信验证
     */
    public function Valid(){
        $echoStr = $_GET["echostr"];
        if($this->CheckSignature()){
            echo $echoStr;
        exit;
        }
    }
}