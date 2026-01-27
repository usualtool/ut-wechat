<?php
namespace usualtool\Wechat;
class Http{
    /**
     * 创建文件夹
     */
    public static function MakeDir($dir,$mode=0777){
        if(is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
        if(!mkdirs(dirname($dir), $mode)) return FALSE;
        return @mkdir($dir, $mode);
    }
    /**
     * 提取所有文章
     */
    function GetAllArticle($data){
        $article = [];
        if(!isset($data['item']) || !is_array($data['item'])){
            return $article;
        }
        foreach($data['item'] as $group){
            if (!isset($group['content']['news_item']) || !is_array($group['content']['news_item'])){
                continue;
            }
            $addtime = $group['update_time'] ?? 0;
            $sendid = $group['article_id'] ?? '';
            foreach ($group['content']['news_item'] as $item) {
                if(!is_array($item)) {
                    continue;
                }
                 $article[] = [
                    'sendid'  =>  $sendid,
                    'title'       =>  $item['title'] ?? '',
                    'digest'      =>  $item['digest'] ?? '',
                    'url'         =>  $item['url'] ?? '',
                    'thumb_url'   =>  $item['thumb_url'] ?? '',
                    'addtime' =>  date("Y-m-d H:i:s",$addtime)
                ];
            }
        }
        return $article;
    }
    /**
     * GET数据
     */
    public static function GetData($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output,true);
    }
    /**
     * POST数据
     */
    public static function PostData($url,$data=''){         
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);   
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);  
        if(!empty($data)){  
            curl_setopt($ch, CURLOPT_POST, TRUE);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        }  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
        $output = curl_exec($ch);  
        curl_close($ch);  
        if(is_null(json_decode($output))){
            return "data:image/png;base64,".base64_encode($output);
        }else{
            return json_decode($output,true);
        }        
    }

}
