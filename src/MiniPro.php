<?php
namespace usualtool\Wechat;
/**
 *小程序专用
*/
class MiniPro{
    private $appline;
    private $appid;
    private $appsecret;
    private $tokenlife=5400;
    public function __construct($appid,$appsecret){
        $this->appline="https://api.weixin.qq.com";
        $this->appid=$appid;
        $this->appsecret=$appsecret;
    }
    /**
     * 获取access_token
     * @param bool $refresh 传 true 跳过缓存强制重取
     * @return string
     */
    public function GetToken($refresh=false){
        $cache=UTF_ROOT."/log/minpro.".$this->appid.".stable_token.json";
        if(!$refresh):
            $info=json_decode((string)@file_get_contents($cache),true);
            if(is_array($info) && !empty($info['access_token']) && isset($info['expires']) && time()<=$info['expires']):
                return $info['access_token'];
            endif;
        endif;
        $token=$this->GetNewToken();
        if($token===''):
            return '';
        endif;
        @file_put_contents($cache,json_encode(array("access_token"=>$token,"expires"=>time()+$this->tokenlife)));
        return $token;
    }
    /**
     * 从 stable 接口取新 access_token
     * @return string 失败返回空串
     */
    public function GetNewToken(){
        $data=json_encode(array(
            "grant_type"=>"client_credential",
            "appid"=>$this->appid,
            "secret"=>$this->appsecret,
            "force_refresh"=>false
        ));
        $res=Http::PostData("{$this->appline}/cgi-bin/stable_token",$data);
        if(is_array($res) && !empty($res['access_token'])):
            $this->tokenlife=isset($res['expires_in']) ? max(60,(int)$res['expires_in']-300) : 5400;
            return $res['access_token'];
        endif;
        return '';
    }
    /**
     * 带 access_token 请求微信接口
     * @param string $path 接口路径
     * @param string $data json 请求体，传 null 表示 GET
     * @param string $query 额外查询串（已 urlencode，用 & 追加）
     * @return mixed
     */
    private function Api($path,$data=null,$query=''){
        $token=$this->GetToken();
        $url="{$this->appline}{$path}?access_token={$token}".($query==='' ? '' : "&".$query);
        $res=is_null($data) ? Http::GetData($url) : Http::PostData($url,$data);
        if(is_array($res) && isset($res['errcode']) && in_array((int)$res['errcode'],array(40001,40014,42001),true)):
            $newtoken=$this->GetToken(true);
            if($newtoken!=='' && $newtoken!==$token):
                $url="{$this->appline}{$path}?access_token={$newtoken}".($query==='' ? '' : "&".$query);
                $res=is_null($data) ? Http::GetData($url) : Http::PostData($url,$data);
            endif;
        endif;
        return $res;
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
        $res = $this->Api("/wxa/checksession",null,"signature=".urlencode($signature)."&openid=".urlencode($openid)."&sig_method=hmac_sha256");
        return $res;
    }
    /**
     * 根据code获取手机号
     * @param string $code 手机号码获取凭证
     * @return array
     */
    public function GetPhone($code){
        $data=json_encode(array("code"=>$code));
        $res = $this->Api("/wxa/business/getuserphonenumber",$data);
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
        $data=json_encode(array(
            "path"=>$path,
            "width"=>$width,
            "auto_color"=>false,
            "is_hyaline"=>false,
            "env_version"=>$version
        ));
        $res = $this->Api("/wxa/getwxacode",$data);
        return $res;
    }
    /**
     * 创建小程序二维码
     * @param string $path 路径
     * @param int $width 宽度
     * @return array
     */
    public function CreatQrcode($path,$width='430'){
        $data=json_encode(array(
            "path"=>$path,
            "width"=>$width
        ));
        $res = $this->Api("/cgi-bin/wxaapp/createwxaqrcode",$data);
        return $res;
    }
    /**
     * 获取页面短链
     * @param string $path 路径
     * @param bool $istime 短链时效，默认短期false,永久true
     * @return array
     */
    public function GetShorLink($path,$istime=false){
        $data=json_encode(array(
            "page_url"=>$path,
                "is_permanent"=>$istime
            ));
        $res = $this->Api("/wxa/genwxashortlink",$data);
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
        $data=json_encode(array(
            "openid"=>$openid,
                "scene"=>$scene,
                "version"=>2,
                "content"=>$content
            ));
        $res = $this->Api("/wxa/msg_sec_check",$data);
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
        $data=json_encode(array(
            "appid"=>$this->appid,
            "openid"=>$openid,
            "scene"=>$scene,
            "ip"=>$ip
        ));
        $res = $this->Api("/wxa/getuserriskrank",$data);
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
            $path = "/datacube/getweanalysisappiddailyretaininfo";
        elseif($type=="week"):
            $path = "/datacube/getweanalysisappidweeklyretaininfo";
            elseif($type=="month"):
                  $path = "/datacube/getweanalysisappidmonthlyretaininfo";
            else:
                  return array("errcode"=>-1,"errmsg"=>"type 只能是 day/week/month");
              endif;
        $data=json_encode(array(
                      "begin_date"=>$startdate,
                    "end_date"=>$enddate
                ));
        $res = $this->Api($path,$data);
        return $res;
    }
    /**
     * 小程序访问数据每日概况
     * @param int $startdate 开始日期 20250301
     * @param enddate $ip 结束日期 20250307
     * @return array
     */
    public function GetDaily($startdate,$enddate){
        $data=json_encode(array(
            "begin_date"=>$startdate,
            "end_date"=>$enddate
        ));
        $res = $this->Api("/datacube/getweanalysisappiddailysummarytrend",$data);
        return $res;
    }
}
