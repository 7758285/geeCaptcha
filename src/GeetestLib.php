<?php
namespace Laravist\GeeCaptcha;
/**
 * 极验行为式验证安全平台，php 网站主后台包含的库文件
 * @author Tanxu
 */
/**
 * Class GeetestLib
 * @package Laravist\GeeCaptcha
 */
class GeeTestLib {

    /**
     *
     */
    const GT_SDK_VERSION = 'php_3.0.0';

    /**
     * @var
     *
     * 极验证 验证 id，在后台可以找到
     */
    protected $captcha_id;
    /**
     * @var
     *
     * 极验证 验证 id，在后台可以找到
     */
    protected $private_key;

    /**
     * GeetestLib constructor.
     * @param $captcha_id
     * @param $private_key
     */
    public function __construct($captcha_id, $private_key)
    {
        $this->captcha_id = $captcha_id;
        $this->private_key = $private_key;
    }

    /**
     *判断极验服务器是否down机
     * @return
     */
    public function pre_process()
    {
        $url = "http://api.geetest.com/register.php?gt=" . $this->captcha_id;
        $challenge = $this->send_request($url);
        if ( strlen($challenge) != 32 ) {
            $this->failback_process();

            return 0;
        }
        $this->success_process($challenge);

        return 1;
    }

    /**
     * @param $challenge
     */
    private function success_process($challenge)
    {
        $result = array(
            'success' => 1,
            'gt' => $this->captcha_id,
            'challenge' => $challenge
        );
        $this->response_str = json_encode($result);
    }

    /**
     *
     */
    private function failback_process()
    {
        $rnd1 = md5(rand(0, 100));
        $rnd2 = md5(rand(0, 100));
        $challenge = $rnd1 . substr($rnd2, 0, 2);
        $result = array(
            'success' => 0,
            'gt' => $this->captcha_id,
            'challenge' => $challenge
        );
        $this->response_str = json_encode($result);
    }

    /**
     * @param $challenge
     * @param $validate
     * @param $seccode
     * @return bool|mixed|string
     */
    public function validate($challenge, $validate, $seccode)
    {
        if ( !$this->check_validate($challenge, $validate) ) {
            return false;
        }
        $data = array(
            "seccode" => $seccode,
            "sdk" => self::GT_SDK_VERSION,
        );
        $url = "http://api.geetest.com/validate.php";
        $codevalidate = $this->post_request($url, $data);
        if ( strlen($codevalidate) > 0 && $codevalidate == md5($seccode) ) {
            return true;
        } else if ( $codevalidate == "false" ) {
            return false;
        } else {
            return $codevalidate;
        }
    }

    /**
     * @param $challenge
     * @param $validate
     * @return bool
     */
    private function check_validate($challenge, $validate)
    {
        if ( strlen($validate) != 32 ) {
            return false;
        }
        if ( md5($this->private_key . 'geetest' . $challenge) != $validate ) {
            return false;
        }

        return true;
    }

    /**
     * @param $url
     * @return mixed|string
     */
    private function send_request($url)
    {
        if ( function_exists('curl_exec') ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = array(
                'http' => array(
                    'method' => "GET",
                    'timeout' => 2,
                )
            );
            $context = stream_context_create($opts);
            $data = file_get_contents($url, false, $context);
        }

        return $data;
    }

    /**
     *解码随机参数
     * @param $challenge
     * @param $string
     * @return
     */
    private function decode_response($challenge, $string)
    {
        if ( strlen($string) > 100 ) {
            return 0;
        }
        $key = array();
        $chongfu = array();
        $shuzi = array("0" => 1, "1" => 2, "2" => 5, "3" => 10, "4" => 50);
        $count = 0;
        $res = 0;
        $array_challenge = str_split($challenge);
        $array_value = str_split($string);
        for ( $i = 0; $i < strlen($challenge); $i ++ ) {
            $item = $array_challenge[ $i ];
            if ( in_array($item, $chongfu) ) {
                continue;
            } else {
                $value = $shuzi[ $count % 5 ];
                array_push($chongfu, $item);
                $count ++;
                $key[ $item ] = $value;
            }
        }

        for ( $j = 0; $j < strlen($string); $j ++ ) {
            $res += $key[ $array_value[ $j ] ];
        }
        $res = $res - $this->decodeRandBase($challenge);

        return $res;
    }


    /**
     * @param $x_str
     * @return
     */
    private function get_x_pos_from_str($x_str)
    {
        if ( strlen($x_str) != 5 ) {
            return 0;
        }
        $sum_val = 0;
        $x_pos_sup = 200;
        $sum_val = base_convert($x_str, 16, 10);
        $result = $sum_val % $x_pos_sup;
        $result = ($result < 40) ? 40 : $result;

        return $result;
    }

    /**
     * @param full_bg_index
     * @param img_grp_index
     * @return
     */
    private function get_failback_pic_ans($full_bg_index, $img_grp_index)
    {
        $full_bg_name = substr(md5($full_bg_index), 0, 9);
        $bg_name = substr(md5($img_grp_index), 10, 9);

        $answer_decode = "";
        // 通过两个字符串奇数和偶数位拼接产生答案位
        for ( $i = 0; $i < 9; $i ++ ) {
            if ( $i % 2 == 0 ) {
                $answer_decode = $answer_decode . $full_bg_name[ $i ];
            } elseif ( $i % 2 == 1 ) {
                $answer_decode = $answer_decode . $bg_name[ $i ];
            }
        }
        $x_decode = substr($answer_decode, 4, 5);
        $x_pos = $this->get_x_pos_from_str($x_decode);

        return $x_pos;
    }

    /**
     * 输入的两位的随机数字,解码出偏移量
     * @param challenge
     * @return
     */
    private function decodeRandBase($challenge)
    {
        $base = substr($challenge, 32, 2);
        $tempArray = array();
        for ( $i = 0; $i < strlen($base); $i ++ ) {
            $tempAscii = ord($base[ $i ]);
            $result = ($tempAscii > 57) ? ($tempAscii - 87) : ($tempAscii - 48);
            array_push($tempArray, $result);
        }
        $decodeRes = $tempArray['0'] * 36 + $tempArray['1'];

        return $decodeRes;
    }

    /**
     * 得到答案
     * @param validate
     * @return
     */
    public function get_answer($challenge, $validate)
    {
        if ( $validate ) {
            $value = explode("_", $validate);
            $ans = $this->decode_response($challenge, $value['0']);
            $bg_idx = $this->decode_response($challenge, $value['1']);
            $grp_idx = $this->decode_response($challenge, $value['2']);
            $x_pos = $this->get_failback_pic_ans($bg_idx, $grp_idx);
            $answer = abs($ans - $x_pos);
            if ( $answer < 4 ) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return 0;
        }

    }

    /**
     * @param $url
     * @param null $postdata
     * @return mixed|string
     */
    public function post_request($url, $postdata = null)
    {
        $data = http_build_query($postdata);
        if ( function_exists('curl_exec') ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if ( !$postdata ) {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $data = curl_exec($ch);
            curl_close($ch);
        } else {
            if ( $postdata ) {
                $url = $url . '?' . $data;
                $opts = array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($data) . "\r\n",
                        'content' => $data
                    )
                );
                $context = stream_context_create($opts);
                $data = file_get_contents($url, false, $context);
            }
        }

        return $data;
    }
}