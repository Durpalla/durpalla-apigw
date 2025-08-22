<?php

namespace Modules\Cart\App\Rules;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Operator\App\Constant\OperatorConstant;
use Modules\Operator\Entities\Operator;

class CartParamValidationRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $operator = Operator::find(request()->input('operator_id'));
            if(is_array($value)) {
                $params = $operator->params->where('category', OperatorConstant::INPUT_METHOD_DYNAMIC);
                if ($params->count()) {
                    foreach ($params as $param) {
                        if ($param['is_required'] && !request()->filled('params.' . $param['key'])) {
                            $fail($param['key'], __("The {$param['label']} is required"));
                        }

                        if(request()->filled('params.' . $param['key'])){
                            if ($param['type'] == 'number' && !is_numeric(request()->input($param['key']))) {
                                if (!is_numeric($value[$param['key']])) {
                                    $fail($param['key'], __("The params :{$param['key']} should be numeric"));
                                }
                            }

                            if (strlen($value[$param['key']]) < $param['min_length']) {
                                $fail($param['key'], __("The param :attribute should be at least {$param['min_length']} characters long"));
                            }

                            if (strlen(request()->input($param['key'])) > $param['max_length']) {
                                $this->message = $param['label'] . ' should not be greater than ' . $param['max_length'];
                            }

                            if (strlen($value[$param['key']]) > $param['max_length']) {
                                $fail($param['key'], __("The param :{$param['key']} should not be greater than {$param['max_length']}"));
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'CART_PARAM_VALIDATION_RULE_EXCEPTION',
                'params' => $value
            ]);
            $fail(__('Internal error!'));
        }
    }
}
