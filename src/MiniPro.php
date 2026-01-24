<?php
namespace usualtool\Wechat;
use usualtool\Wechat\Http;
/**
 *小程序专用
*/
class MiniPro{
    public function __construct($appid,$appsecret,$token=''){
			  $this->appline="https://api.weixin.qq.com";
        $this->appid=$appid;
        $this->appsecret=$appsecret;
        $this->token=$token;
    }
    /**
     * access_token
     * @return string
     */
    public function GetToken(){
        $file = file_get_contents(UTF_ROOT."/log/minpro.".$this->appid.".token.json",true);
        $result = json_decode($file,true);
        if($result==null || !isset($result['expires']) || time()>$result['expires']):
            $data = array();
            $data['access_token'] = $this->GetNewToken();
            $data['expires']=time()+5400;0
							.
            $jsonStr =  json_encode($data);
            file_put_contents(UTF_ROOT."/log/minpro.".$this->appid.".token.json",$jsonStr);
            return $data['access_token'];
        else:
            return $result['access_token'];
        endif;
    }
    public function GetNewToken(){
        $url = "{$this->appline}/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->appsecret}";
        $access_token_Arr =  $this->GetData($url);
        return $access_token_Arr['access_token'];
    }
    /**
     * 登录凭证校验
     * @param string $code 获取的登录code
     * @return array
     */
    public function ChkLogin($code){
        $url = "{$this->appline}/sns/jscode2session?appid={$this->appid}&secret={$this->appsecret}&js_code={$code}&grant_type=authorization_code";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 登录状态
     * @param string $openid 用户标识
     * @param string $signature 登录签名
		 * test:signature = hmac_sha256(session_key, "")
     * @return array
     */
    public function LoginState($openid,$signature){
        $url = "{$this->appline}/wxa/checksession?access_token={$this->GetToken()}&signature={$signature}&openid={$openid}&sig_method=hmac_sha256";
        $res = Http::GetData($url);
        return $res;
    }
    /**
     * 根据code获取手机号
     * @param string $code 手机号码获取凭证
     * @return array
     */
    public function GetPhone($code){
        $url = "{$this->appline}/wxa/business/getuserphonenumber?access_token={$this->GetToken()}";
        $data=json_encode(array("code"=>$code));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 小程序码
     * @param string $path 路径
     * @param int $width 宽度
     * @param string $version 小程序版本
     * @return array
     */
    public function GetQrcode($path,$width='430',$version='release'){
        $url = "{$this->appline}/wxa/getwxacode?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "path"=>$path,
				    "width"=>$width,
				    "auto_color"=>false,
				    "is_hyaline"=>false,
				    "env_version"=>$version
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 创建小程序二维码
     * @param string $path 路径
     * @param int $width 宽度
     * @return array
     */
    public function CreatQrcode($path,$width='430'){
        $url = "{$this->appline}/cgi-bin/wxaapp/createwxaqrcode?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "path"=>$path,
				    "width"=>$width
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 获取页面短链
     * @param string $path 路径
     * @param bool $istime 短链时效，默认短期false,永久true
     * @return array
     */
    public function GetShorLink($path,$istime=false){
        $url = "{$this->appline}/wxa/genwxashortlink?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "page_url"=>$path,
					  "is_permanent"=>$istime
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 检测文本内容
     * @param string $openid 用户标识
     * @param int $scene 场景 1资料 2评论 3论坛 4社交日志
     * @param string $content 文本内容
     * @return array
     */
    public function CheckMsg($openid,$scene,$content){
        $url = "{$this->appline}/wxa/msg_sec_check?access_token={$this->GetToken()}";
        $data=json_encode(array(
				    "openid"=>$openid,
					  "scene"=>$scene,
					  "version"=>2,
					  "content"=>$content
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 用户安全等级
     * @param string $openid 用户标识
     * @param int $scene 场景 0注册 1营销作弊
     * @param string $ip 用户IP
     * @return array
     */
    public function GetUserRank($openid,$scene,$ip){
        $url = "{$this->appline}/wxa/getuserriskrank?access_token={$this->GetToken()}";
        $data=json_encode(array(
					  "appid"=>$this->appid,
				    "openid"=>$openid,
					  "scene"=>$scene,
					  "ip"=>$ip
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 小程序日、周、月留存数据
     * @param string $type 日day,周week,月month
     * @param int $startdate 开始日期 20250301
     * @param enddate $ip 结束日期 20250307
     * @return array
     */
    public function GetRetain($type,$startdate,$enddate){
			  if($type=="day"):
			      $url = "{$this->appline}/datacube/getweanalysisappiddailyretaininfo?access_token={$this->GetToken()}";
			  elseif($type=="week"):
            $url = "{$this->appline}/datacube/getweanalysisappidweeklyretaininfo?access_token={$this->GetToken()}";
			  elseif($type=="month"):
			      $url = "{$this->appline}/datacube/getweanalysisappidmonthlyretaininfo?access_token={$this->GetToken()}";
			  endif;
        $data=json_encode(array(
					  "begin_date"=>$startdate,
				    "end_date"=>$enddate
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
    /**
     * 小程序访问数据每日概况
     * @param int $startdate 开始日期 20250301
     * @param enddate $ip 结束日期 20250307
     * @return array
     */
    public function GetDaily($startdate,$enddate){
			  $url = "{$this->appline}/datacube/getweanalysisappiddailysummarytrend?access_token={$this->GetToken()}";
        $data=json_encode(array(
					  "begin_date"=>$startdate,
				    "end_date"=>$enddate
				));
        $res = Http::PostData($url,$data);
        return $res;
    }
}