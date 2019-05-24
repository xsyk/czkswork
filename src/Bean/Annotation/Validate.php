<?php
namespace Swork\Bean\Annotation;

/**
 * 请求参数验证器注释入口
 * @Annotation
 */
class Validate
{
    const Required = 100;
    const Number = 101;        //校验 0-9
    const Letter = 102;        //校验 A-Za-z
    const LetterNumber = 103;  //校验 A-Za-z0-9 字符
    //const Text = 104;          //校验 正则 \w 字符
    const TextKey = 105;       //校验 A-Za-z0-9_-, 字符
    const NotZero = 106;       //检验 不等于0
    const Email = 107;         //校验 邮箱 格式
    const Mobile = 108;        //校验 手机号码 格式
    const Url = 109;           //校验 http:// 或 https:// 格式
    const Date = 110;          //校验 YYYY-MM-DD 的格式
    const Time = 111;          //校验 hh:mm(:ss) 的格式
    const DateTime = 112;      //校验 YYYY-MM-DD hh:mm(:ss) 的格式
    const Decimal = 113;       //小数（含正负数）
    const Digits = 114;        //整数（含正负数，不以0开头的数字）

    const Equal = 201;            //检验 两个字段的值是否相同
    const Greater = 202;          //检验 后面字段是否大于前端字段
    const Lesser = 203;           //检验 后面字段是否小于前端字段
    const GreaterZero = 204;      //检验 大于0
    const GreaterEqualZero = 205; //检验 大于等于0
    const LessZero = 206;         //检验 小于0
    const LessEqualZero = 207;    //检验 小于等于0

    const Length = 301;  //字符长度 [1-6](限制1-6位), [1-](至少1位), [-6](最多6位)
    const Range = 302;   //值范围  [1-6](最小值1，最大值6), [1-](最小值1), [-6](最大值6)
    const Ins = 303;     //值范围  [1|2|6](值只通是1或2或6，其值不允许)

    public function __construct()
    {

    }
}
