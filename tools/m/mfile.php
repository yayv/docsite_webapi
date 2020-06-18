<?php

class mfile extends model
{
   
    function __construct($filename=false)
    {
        $this->eof = false;

        $this->status = [
            'not_start', // no file opened
            'in_head', // in file , not in api section
            'in_menu', // in menu section
            'in_body',  // in api section
            'in_model', // in api model section
            'in_api', // in api define section
            'end_file'
        ];

        $this->INFOKEYS = ["No","URL","NAME","METHOD","FORMAT","REQUEST","RESPONSE","TEST","NOTE","TODO","STATUS"];
        
        $this->arrDatas = array(
            "CODE"=>'',
            "NAME"=>'',
            "URL"=>'',
            "METHOD"=>"",
            "FORMAT"=>"", // JSON/FORM
            "REQUEST"=>"",
            "RESPONSE"=>"",
            "NOTE"=>"",
            "STATUS"=>"",
            "TODO"=>"",
            "lastModify"=>"",
        );

        $this->statusStack = [];
        $this->status = 'not_start';

        if($filename)
            $this->setFile($filename);
    }

    function parseBody($methodNo, $methodName, $lines)
    {
        $arrExtra = array();
        $iskey = false;
        $key = false;   

        if($methodNo=='') {print_r(array($methodNo,$methodName,$lines));die('kii');}
        echo "API:",$methodNo,"[",$methodName,"]\n";
        #print_r($lines);
        #echo "[------------]\n";

        foreach($lines as $k=>$v)
        {
            # echo $v,"\n";
            # continue;
            if($ret = preg_match("/```/",$v, $matches))
            {
                continue ;
            }

            $ret = preg_match("/(^[ a-zA-Z]*):(.*)/",$v, $matches);
            if($ret)
            {
                $key = strtoupper($matches[1]);

                // end last key, or start new key
                if( array_key_exists($key, $arrDatas) )
                {
                    $iskey = true;
                    $arrDatas[$key] = $matches[2];
                }
                else
                {
                    $iskey = false;
                    $arrExtra[$key] = $matches[2];
                }
            }
            else
            {
                if(!$key) continue ; // 尚未进入API

                if($iskey)
                    $arrDatas[$key] .= $v;
                else
                    $arrExtra[$key] .= $v;  
            }
        }

        #if($methodNo=='U18')
        {
            checkKeyValue($methodName, $arrDatas, $arrExtra);
        }

        if(count($arrDatas)==0)
            return false;
        else
            return $arrDatas;
    }

    function checkKeyValue($api, $arrDatas, $arrExtra)
    {
        $MUSTKEYS = ["URL","REQUEST","RESPONSE"];
        foreach($MUSTKEYS as $v)
        {
            if(!isset($arrDatas[$v]) || ""==$arrDatas[$v])
            {
                // TODO: wrong
                echo "\tKEY:",$v, " 必须设置\n";
                #debug_print_backtrace();
                return false;
            }
        }

        if(''!=trim($arrDatas['REQUEST']))
        {
            $req = json_decode($arrDatas['REQUEST']);
            $msg = json_last_error_msg();
            if($msg!='No error') 
            {
                echo "  REQUEST: \t",$msg, "\n";
                print_r($req);
            }
        }
        else
        {
            // DO NOTHING
        }

        $res = json_decode($arrDatas['RESPONSE']);
        $msg = json_last_error_msg();
        if($msg!='No error') 
        {
            echo "  RESPONSE: \t",$msg, "\n";
            print_r($res);
        }

        if(isset($arrDatas['TODO']) && ''!=$arrDatas['TODO'])
        {
            echo '  待完成:',$arrDatas['TODO'],"\n";
        }
    }

    public function parseModel($section)
    {
        $ret = preg_match("/^##[ \t]*([a-zA-Z]*[a-zA-Z0-9\-\_]*):(.*)/", $section[0], $matches);
        if($ret)
        {
            // ["code"=>'test'.uniqid(),"name"=>'测试'.uniqid(),'extraInfo'=>'','lastModify'=>''];
            $model = ['code'=>$matches[1], 'name'=>trim($matches[2]), 'lastModify'=>date('Y-m-d H:i:s')];
        }
        else
        {
            $model = false;
        }

        return $model;
    }

    public function parseAPILine($section)
    {
        $ret = preg_match("/^###[ \t]*([a-zA-Z]*[a-zA-Z0-9\-\_]*):(.*)/", $section[0], $matches);
        if($ret)
        {
            $api = ["code"=>$matches[1],"name"=>trim($matches[2]),'lastModify'=>date('Y-m-d H:i:s')];
        }
        else
        {
            $api = false;
        }
        
        return $api;
    }

    public function parseAPI($section)
    {
        $api = [];
        // 1. find 1 ```
        // 2. find 2 ```
        $start = false;
        $end = false;
        foreach($section as $k=>$v)
        {
            if(trim($v)=='```')
            {
                if($start)
                    $end = $k;
                else
                    $start = $k;
            }
        }

        $arrDatas = array(
            "NO"=>'',
            "NAME"=>'',
            "URL"=>'',
            "REQUEST"=>"",
            "FORMAT"=>"", // JSON/FORM
            "RESPONSE"=>"",
            "NOTE"=>"",
            "STATUS"=>"",
            "TODO"=>"",
            "METHOD"=>"",
            "TEST"=>""
        );
        $arrExtra= [];
                // merge head
        $api['head'] = implode('',array_slice($section, 1,$start-1));

        $body = array_slice($section, $start+1,$end-$start-1);

        // merge tail 
        $api['tail'] = implode('',array_slice($section, $end+1));

        foreach($body as $k=>$v)
        {
            $ret = preg_match("/(^[ \ta-zA-Z0-9]*):(.*)/",$v, $matches);
            if($ret)
            {
                $key = strtoupper(trim($matches[1]));
                #echo $key,":",$matches[2],"\n";
                // end last key, or start new key
                if( array_key_exists($key, $arrDatas) )
                {
                    $iskey = true;
                    $arrDatas[$key] = $matches[2];
                }
                else
                {
                    $iskey = false;
                    $arrExtra[$key] = $matches[2];
                }
            }
            else
            {
                // 尚未进入API, TODO 考虑追加到 head 里
                if(!$key)
                    $api['head'] .= $v;

                if($iskey)
                    $arrDatas[$key] .= $v;
                else
                    $arrExtra[$key] .= $v;  
            }
        }        
        
        print_r([$arrDatas,$arrExtra]);
        die();
        return $api;
    }

    public function getSectionType($line)
    {
        $t = [];
        $sectypes = [
            'startLine' => "/\#[ \t]*Interface Start/",
            'modelTitleLine' => "/^##[ \t]*(([a-zA-Z]*[a-zA-Z0-9\-_]*):)([^ ]*)[ ]*(.*)/",
            'apiTitleLine' => "/^###[ \t]*(([a-zA-Z]*[a-zA-Z0-9\-_]*):)[^ ]*(.*)/",
            'apiBodyBorder' => "/```/",
            'menu' =>"/##[ \t]*MENU/"
        ];

        $nomatch = true;
        foreach($sectypes as $k=>$v)
        {
            $ret = preg_match($v, $line, $matches);
            if($ret)
            {
                $nomatch = false;
                $t[] = $k;
            }
        }

        if($nomatch)
            return ['noMatch'];

        return $t;
    }

    public function setFile($file)
    {
        $this->filename = $file; 
        $this->lines = file($file);
        $this->idx = 0 ;
        $this->eof = false;
        $this->status = 'in_file';
    }

    public function getNextSection()
    {
        $result = [$this->lines[$this->idx]];
        
        $t = $this->getSectionType($this->lines[$this->idx]);

        for( $i=$this->idx+1; $i<count($this->lines); $i++ )
        {
            $ret = preg_match("/^[ ]*\#+/", $this->lines[$i], $matches);
            if(!$ret)
            {
                #preg_match("/^[ ]*\#+/", $this->lines[$i], $matches);
                $result[] = $this->lines[$i];
            }
            else
            {

                #print_r([$i,$this->idx,count($this->lines), $this->lines[$i], $matches]);
                $this->idx = $i;
                return [$result, $t];
            }
        }

        $this->eof = true;
        return [$result,$t];
    }

    public function isEof()
    {
        return $this->eof;
    }

    // 以下为从数据库dump成markdown文件需要用到的函数

    public function buildMenu($models, $apis)
    {
        #return  ["1", '2','3'];
        $menu = [];
        #print_r([$models, $apis]);
        foreach($models as $k=>$v)
        {
            #echo $v['name'],"\n";
            $menu[] = "*".$v['name']."*";
            foreach($apis as $kk=>$vv)
            {
                #echo "\t",$vv['name'],"\n";
                if($vv['model']==$v['code'])
                $menu[] = "- [ ]".$vv['model'].$vv['code'].':'.$vv['name'];
            }
        }

        return $menu;
    }

    // 获取文本描述的 模块信息
    public function getModelString($model)
    {    
        return "## ${model['code']}: ${model['name']}\n";
    }

    // 获取文本描述的 API 信息
    public function getApiString($model, $api)
    {
        $str = "### ${model['code']}${api['code']}: ${api['name']}\n";
        $str.= "```\n";
        $str.= "CODE:${api['url']}\n";
        $str.= "NAME:${api['url']}\n";
        $str.= "NOTE:${api['response']}\n";

        $str.= "URL:${api['url']}\n";
        $str.= "METHOD:${api['method']}\n";
        $str.= "FORMAT:${api['response']}\n";
        
        $str.= "REQUEST:${api['request']}\n";
        $str.= "RESPONSE:${api['response']}\n";
        $str.= "STATUS:${api['response']}\n";
        $str.= "TODO:${api['response']}\n";
        $str.= "```\n";

        return $str;
    }
}


