<?php
namespace Swork\Server\Http;

use Demo\App\Exception\SworkException;
use Swork\Bean\Annotation\Validate;
use Swork\Bean\Holder\ValidateHolder;

class Validator
{
    public static function checkRequest(string $cls, string $method, Argument $argument)
    {
        //提取当前类的检验器和方式
        $validates = ValidateHolder::getClass($cls)[$method] ?? false;
        if ($validates == false)
        {
            return;
        }

        //根据每个参数处理
        foreach ($validates as $name => $calls)
        {
            //获取外部数据
            $method = $calls[0]['method'];

            //获取数据值
            $val = null;
            if ($method == 1)
            {
                $val = $argument->get($name);
            }
            else
            {
                $val = $argument->post($name);
            }

            //检验当前字段这下所有规则
            foreach ($calls as $call)
            {
                $target = $call['target'];
                $void = $call['void'];
                if(in_array($void, ['Equal', 'Greater', 'Lesser']))
                {
                    if(isset($call['match'][0]) && $call['match'][0] == '')
                    {
                        $call['val2'] = $call['match'][1] ?? '';
                    }
                    else
                    {
                        $name2 = $call['match'][1] ?? '';
                        $call['val2'] = $method == 1 ? $argument->get($name2) : $argument->post($name2);
                    }
                }
                $target::$void($name, $val, $call);
            }
        }
    }

    /**
     * 检查值是为空
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function required(string $name, $val, array $call)
    {
        if ($val == '')
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不能为空";
            }
            throw new SworkException($message, Validate::Required);
        }
    }

    /**
     * 检查值是否为数字
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function number(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是纯0-9的数字";
            }
            throw new SworkException($message, Validate::Number);
        }
    }

    /**
     * 检查值是否为字母（不分大小写）
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function letter(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^[a-zA-Z]+$/', $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是纯字母";
            }
            throw new SworkException($message, Validate::Letter);
        }
    }

    /**
     * 检查值是否为字母（不分大小写）
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function letterNumber(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是字母数字";
            }
            throw new SworkException($message, Validate::LetterNumber);
        }
    }

    /**
     * 检查值是否为不含特殊字段的文本
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function textKey(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^[A-Za-z0-9_\-,]+$/', $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是合法文本";
            }
            throw new SworkException($message, Validate::TextKey);
        }
    }

    /**
     * 检查值是否为不等于0
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function notZero(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            throw new SworkException("[$name]不能包含非数字字符", Validate::Letter);
        }
        if ($val == 0)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不能等于0";
            }
            throw new SworkException($message, Validate::NotZero);
        }
    }

    /**
     * 检查邮箱格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function email(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if(!filter_var($val, FILTER_VALIDATE_EMAIL))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]邮箱格式不正确";
            }
            throw new SworkException($message, Validate::Email);
        }
    }

    /**
     * 检查手机号码格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function mobile(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if(!preg_match('/^1([356789]\d{9}|47\d{8})$/', $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]手机号码不正确";
            }
            throw new SworkException($message, Validate::Mobile);
        }
    }

    /**
     * 检查http:// 或 https:// 格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function url(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if(!filter_var($val, FILTER_VALIDATE_URL))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]url格式不正确";
            }
            throw new SworkException($message, Validate::Url);
        }
    }

    /**
     * 检查YYYY-MM-DD 的格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function date(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        $message = $call['message'];
        if ($message == '')
        {
            $message = "[$name]日期格式不是有效的格利高里日期";
        }
        //匹配日期格式
        if (preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $val, $parts))
        {
            //检测是否为日期
            if (!checkdate($parts[2], $parts[3], $parts[1]))
            {
                throw new SworkException($message, Validate::Date);
            }
        }
        else
        {
            throw new SworkException($message, Validate::Date);
        }
    }

    /**
     * 检查hh:mm(:ss) 的格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function time(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match("/^([0-1]\d|2[0-4]):([0-5]\d)(:[0-5]\d)?$/", $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]时间格式不正确hh:mm(:ss)";
            }
            throw new SworkException($message, Validate::Time);
        }
    }

    /**
     * 检查YYYY-MM-DD hh:mm(:ss) 的格式
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function dateTime(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match("/^([12]\d\d\d)-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[0-1]) ([0-1]\d|2[0-4]):([0-5]\d)(:[0-5]\d)?$/", $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]日期时间格式不正确YYYY-MM-DD hh:mm(:ss)";
            }
            throw new SworkException($message, Validate::DateTime);
        }
    }

    /**
     * 检查小数（含正负数）
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function decimal(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if(!preg_match("/^-?\d+\.\d+$/", $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是浮点数";
            }
            throw new SworkException($message, Validate::Decimal);
        }
    }

    /**
     * 整数（含正负数，不以0开头的数字）
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function digits(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if(!preg_match("/^-?\d+$/", $val))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不是整数";
            }
            throw new SworkException($message, Validate::Digits);
        }
    }

    /**
     * 检验 两个字段的值是否相同
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function equal(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        $val2 = $call['val2'];
        if($val != $val2)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]和[{$call['match'][1]}]值不相同";
            }
            throw new SworkException($message, Validate::Equal);
        }
    }

    /**
     * 检验 后面字段是否大于前端字段
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function greater(string $name, $val, array $call)
    {
        $val2 = $call['val2'];
        if ($val == '' || $val2 == '')
        {
            return;
        }
        if($val >= $val2)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]应该小于[{$call['match'][1]}]";
            }
            throw new SworkException($message, Validate::Greater);
        }
    }

    /**
     * 检验 后面字段是否小于前端字段
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function lesser(string $name, $val, array $call)
    {
        $val2 = $call['val2'];
        if ($val == '' || $val2 == '')
        {
            return;
        }
        if($val <= $val2)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]应该大于[{$call['match'][1]}]";
            }
            throw new SworkException($message, Validate::Lesser);
        }
    }

    /**
     * 检查值是否为大于0
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function GreaterZero(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            throw new SworkException("[$name]不能包含非数字字符", Validate::Letter);
        }
        if ($val <= 0)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]需要大于0";
            }
            throw new SworkException($message, Validate::Letter);
        }
    }

    /**
     * 检查值是否为大于等于0
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function GreaterEqualZero(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            throw new SworkException("[$name]不能包含非数字字符", Validate::Letter);
        }
        if ($val < 0)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]需要大于等于0";
            }
            throw new SworkException($message, Validate::Letter);
        }
    }

    /**
     * 检查值是否为小于0
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function LessZero(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            throw new SworkException("[$name]不能包含非数字字符", Validate::Letter);
        }
        if ($val >= 0)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]需要小于0";
            }
            throw new SworkException($message, Validate::Letter);
        }
    }

    /**
     * 检查值是否为小于等于0
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function LessEqualZero(string $name, $val, array $call)
    {
        if ($val == '')
        {
            return;
        }
        if (!preg_match('/^\d+$/', $val))
        {
            throw new SworkException("[$name]不能包含非数字字符", Validate::Letter);
        }
        if ($val > 0)
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]需要小于等于0";
            }
            throw new SworkException($message, Validate::Letter);
        }
    }

    /**
     * 检查值是否在指定范围内
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws
     */
    public static function ins(string $name, $val, array $call)
    {
        $vals = $call['match'];
        if (!in_array($val, $vals))
        {
            $message = $call['message'];
            if ($message == '')
            {
                $message = "[$name]不在指定范围内";
            }
            throw new SworkException($message, Validate::Ins);
        }
    }

    /**
     * 检查值长度
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws SworkException
     */
    public static function length(string $name, $val, array $call)
    {
        //如果有空，则不检查
        if ($val == '')
        {
            return;
        }

        //提取长度参数
        $vals = $call['match'];
        $count = count($vals);
        if ($count == 0)
        {
            return;
        }

        $message = $call['message'];
        $length = mb_strlen($val);

        $msg = "[$name]长度应该";
        $flg = true;
        if($count == 1 && $length != $vals[0])
        {
            $flg = false;
            $msg .= '等于'.$vals[0];
        }
        else
        {
            if($vals[0] != '' && $length < $vals[0])
            {
                $flg = false;
                $msg .= '大于'.$vals[0];
            }
            if($vals[1] != '' && $length > $vals[1])
            {
                $flg = false;
                $msg .= '小于'.$vals[1];
            }
        }

        if(!$flg)
        {
            $message = $message == '' ? $msg : $message;
            throw new SworkException($message, Validate::Length);
        }
    }

    /**
     * 检查值范围
     * @param string $name 目标检查字段
     * @param string|int $val 待检查的值
     * @param array $call 检查规划
     * @throws SworkException
     */
    public static function range(string $name, $val, array $call)
    {
        $vals = $call['match'];
        $message = $call['message'];
        $count = count($vals);
        if ($val == '')
        {
            return;
        }
        if(!is_numeric($val))
        {

            throw new SworkException("[$name]值不是数字", Validate::Range);
        }
        if ($count == 0)
        {
            return;
        }
        $msg = "[$name]值应该";
        $flg = true;
        if($count == 1 && $val != $vals[0])
        {
            $flg = false;
            $msg .= '等于'.$vals[0];
        }
        else
        {
            if($vals[0] != '' && $val < $vals[0])
            {
                $flg = false;
                $msg .= '大于'.$vals[0];
            }
            if($vals[1] != '' && $val > $vals[1])
            {
                $flg = false;
                $msg .= '小于'.$vals[1];
            }
        }

        if(!$flg)
        {
            $message = $message == '' ? $msg : $message;
            throw new SworkException($message, Validate::Range);
        }
    }

    public static function CheckDefined()
    {

    }
}
