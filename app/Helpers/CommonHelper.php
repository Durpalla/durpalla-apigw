<?php

namespace App\Helpers;

use App\Gateways\GatewayInterface;
use App\Gateways\NotExist;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Billing\Entities\Billing;
use Modules\Customer\Entities\Customer;
use Modules\Payment\Entities\Payment;

class CommonHelper
{
    public static function parsePaginator($collections = null): array
    {
        return [
            'from' => $collections->firstItem() ?? 0,
            'to' => $collections->lastItem() ?? 0,
            'per_page' => $collections->perPage(),
            'current_page' => $collections->currentPage(),
            'last_page' => $collections->lastPage(),
            'total' => $collections->total(),
            'data' => collect($collections->items())->map(function ($item) {
                return $item->format();
            })
        ];
    }

    public static function purseGateway($gateway): GatewayInterface
    {
        $gatewayName = $gateway->class_name ?? NotExist::class;
        if (!class_exists($gatewayName)) {
            throw new \Exception('Gateway not properly configured', 500);
        }
        return new $gatewayName();
    }

    public static function hasPermission(array $permissions, $roles = ['admin']): bool
    {
        $user = request()->user();
        return $user->hasAnyRole($roles) || $user->canAny($permissions);
    }

    public static function isBillGenerateAble(Customer $customer, $isDynamicBilling = false): bool
    {
        try {
            if($customer->package) {
                if ($isDynamicBilling) {
                    if ($customer->bill) {
                        //check if current bill is new bill
                        if ($customer->bill->created_at->diffInDays(now()) <= 14) {
                            return false;
                        }

                        if ($customer->isSpecial()) {
                            if (
                                date('Y-m-d', strtotime($customer->bill->bill_month)) >= $customer->extended_due_date->format('Y-m-d')
                                || $customer->extended_due_date->format('Y-m') == date('Y-m')
                            ) {
                                return false;
                            }
                        } else {
                            if ($customer->bill->dues >= (($customer->package->price - $customer->discounted_amount) * 0.75)
//                                || (date('F-Y') == $customer->bill->bill_month)
                                || (date('Y-m-d', strtotime($customer->bill->bill_month)) > now()->addDays(16)->format('Y-m-d'))
                                || ($customer->extended_due_date > now()->format('Y-m-d') && $customer->extended_due_date->diffInDays(now()) > 16)) {
                                return false;
                            }
                        }
                    }
                } else {
                    if ($customer->bill && date('Y-m-d', strtotime($customer->bill->bill_month)) >= now()->format('Y-m-01')) {
                        return false;
                    }
                }
            } else {
                return false;
            }
            return true;
        } catch (\Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    public static function getBillMonth($dueDate, $format = 'F-Y'): string
    {
        return ($dueDate >= now()->format('Y-m-01')) ? date($format, strtotime($dueDate)) : now()->format($format);
    }

    public static function isNotifiable($customer, $resellerSmsEnabled, $minDues): bool
    {
        return ($customer->bill && $customer->bill->dues >= $minDues) && (is_null($customer->reseller_id) || $resellerSmsEnabled);
    }

    public static function getGatewayIds(): array
    {
        return explode(',', str_replace(' ', '', getOption('gateway_user_ids'))) ?? [];
    }

    public static function filterModel($query, $request)
    {
        if ($request->filled('date_from')) {
            $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $request->input('date_from'))->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->date_to) {
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $request->input('date_to'))->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        return $query;
    }

    public static function generateCustomerID($name, $mobile): string
    {
        $str = Str::slug(trim($name), '_') . '_' . Str::random(6) . '_' . substr($mobile, -4);
        for (;;) {
            if(Customer::where('customerID', $str)->count()) {
                self::generateCustomerID($name, $mobile);
            } else {
                break;
            }
        }
        return strtolower($str);
    }

    public function checkNid($nid, $dob)
    {
        $url = config('porichoy.live.autofill_v2_new');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "nidNumber": "' . $nid . '",
                "englishTranslation": true,
                "dateOfBirth": "' . $dob . '"
            }',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . config('porichoy.key'),
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        dd($data);
    }

    public static function getSearchKeyword($user, Request $request)
    {
        return ($user->hasPermissionTo('customer-search-only')) ? $request->input('keyword') ?? 'test' : $request->input('keyword');
    }

    public static function searchByKeyword($user, Request $request, Builder $query, $isSearchOnly = false): void
    {
        $keyword = self::getSearchKeyword($user, $request);
        if ($keyword) {
            if (is_numeric($keyword)) {
                if (strlen($keyword) > 9) {
                    $query->whereHas('user', function ($q) use ($keyword, $isSearchOnly) {
                        if ($isSearchOnly) {
                            $q->where('mobile', $keyword);
                        } else {
                            $q->where('mobile', 'LIKE', "%$keyword%");
                        }
                    });
                } else {
                    $query->where('id', (int)$keyword);
                }
            } else {
                $query->where(function ($q) use ($keyword, $isSearchOnly, $user) {
                    if ($isSearchOnly && !$user->type != 'admin') {
                        $q->where('customerID', $keyword);
                    } else {
                        $q->where('customerID', 'LIKE', '%' . $keyword . '%');
                        $q->orWhereHas('user', function ($q) use ($keyword) {
                            $q->where('name', 'LIKE', "%$keyword%");
                        });
                    }
                });
            }
        }
    }

    public static function getMyBalance(): float
    {
        return DB::table('funds')->where('user_id', auth()->user()->id)->first()->amount ?? 0;
    }

    public static function customerDues($dues): int
    {
        return $dues > 0 ? self::numberFormat($dues) : 0;
    }

    public static function customerAdvance($dues): int
    {
        return $dues < 0 ? self::numberFormat($dues) : 0;
    }

    public static function parseTemplate($string, $array)
    {
        foreach ($array as $key => $value) {
            $string = preg_replace("/{" . $key . "}/i", $value, $string);
        }

        return $string;
    }

    public static function _customerIDExist($id, $customerID): bool
    {
        return (bool)Customer::where('id', '!=', $id)->where('customerID', $customerID)->count();
    }

    public static function _mobileExist($id, $mobile): bool
    {
        return (bool)Customer::where('id', '!=', $id)->where('primary_contact', $mobile)->count();
    }

    public static function _usernameExist($userId, $username): bool
    {
        return (bool)User::where('id', '!=', $userId)->where('username', $username)->count();
    }

    public static function _emailExist($userId, $email): bool
    {
        return (bool)User::where('id', '!=', $userId)->where('email', $email)->count();
    }

    public static function billNotExist($customer, $month = null): bool
    {
        $month = ($month == null) ? date('F-Y') : $month;
        return Billing::where(['bill_month' => $month, 'customer_id' => $customer->id])->count() === 0;
    }

    public static function billExist($customer, $month = null): bool
    {
        $month = ($month == null) ? date('F-Y') : $month;
        return Billing::where(['bill_month' => $month, 'customer_id' => $customer->id])->count() > 0;
    }

    public static function numberFormat($number)
    {
        return self::{getOption('number_format', 'ceil')}($number);
    }

    public function displayFormat($number): string
    {
        return number_format($number, 2);
    }

    public static function customerStatus($status)
    {
        return config('common.customer.statuses')[$status];
    }

    public static function calculateBill(Customer $customer, $previous_dues = 0, $service_charge = 0)
    {
        $bills = 0;
        $package_bill = self::getCustomerPackagePrice($customer);
        $billType = getOption('bill_type', 'daily');
        $remainingDays = self::remainingDays($customer);
        if (date('F-Y', strtotime($customer->created_at)) == date('F-Y', strtotime($customer->extended_due_date ?? date('Y-m-d')))) {
            if (!$customer->reseller) {
                $bills += $customer->setup_charge;
                $bills += $customer->cable_cost;
                $bills += $customer->initial_dues;
                $bills += $service_charge;
            }
            $bills = self::getCalculation($billType, $remainingDays, $package_bill, $bills, $previous_dues);
        } else {
            $bills = self::getCalculation($billType, $remainingDays, $package_bill, $bills, $previous_dues);
        }

        return self::numberFormat($bills);
    }

    private static function getCalculation($billType, $remainingDays, $package_bill, $bills, $previous_dues)
    {
        switch ($billType) {
            case 'bi-monthly':
                if (getOption('billing_cycle') == '1') {
                    $bills += $package_bill;
                } else {
                    if ($remainingDays <= 25) {
                        $bills += ($package_bill / 2);
                    } elseif (date('d') > 25) {
                        $bills += 0;
                    } else {
                        $bills += $package_bill;
                    }
                }
                break;
            case 'quarterly':
                if (date('d') <= 7) {
                    $bills += $package_bill;
                } elseif (date('d') > 7 && date('d') <= 20) {
                    $bills += ($package_bill / 2);
                } else {
                    $bills += 0;
                }
                break;
            default :
                $bills += ($package_bill / date('t')) * $remainingDays;
                break;
        }
        return self::numberFormat($bills + $previous_dues);
    }

    public static function updateDueDate(Customer $customer, $dues = 0, $updateDueDate = true): bool
    {
        return $customer->update(['extended_due_date' => self::getExtendedDueDate($customer, $dues, $updateDueDate)]);
    }

    public static function remainingDays($customer): int
    {
        $remainingDays = (int) (date('t') - date('d')) + 1;
        if(self::isDynamicBilling()) {
            if($customer->extended_due_date > now()) {
                $remainingDays = now()->diffInDays($customer->extended_due_date) + 1;
            } else {
                $remainingDays = 0;
            }
        }

        return $remainingDays;
    }

    public static function calculateIncreasedAmount($customer, $newPackage, $currentPackage): float
    {
        $isDynamicBilling = self::isDynamicBilling();

        if(self::isBillGenerateAble($customer, $isDynamicBilling)) {
            app(BillingService::class)->generateBill($customer, 0);
            $customer->load(['bill']);
        }

        if (!$customer->bill) {
            return 0;
        }

        $diff = $newPackage->price - $currentPackage['price'];

        if ($customer->bill->dues >= $currentPackage->price) {
            return $diff;
        }

        $oneDayAmount = $diff / now()->format('t');

        $remainDays = CommonHelper::remainingDays($customer);
        $amount = CommonHelper::numberFormat($oneDayAmount * $remainDays);

        if ($isDynamicBilling && $remainDays > 1) {
            $amount += $newPackage->price;
        }

        return $amount;
    }

    public static function getMethodName($request): string
    {
        return explode('@', (string)$request->route()->getActionName())[1] ?? 'info';
    }

    public static function extendSpecialCustomerDueDate($customer)
    {
        $dueDate = date('d', getOption('auto_disable_date', 10));
        $extendedDueDate = date('Y-') . date('m-', strtotime("+1 month")) . $dueDate;
        if (self::isDynamicBilling()) {
            $extendedDueDate = now()->addMonth()->format('Y-m-d');
            if ($customer->extended_due_date > now()->format('Y-m-d')) {
                $extendedDueDate = $customer->extended_due_date->addMonth()->format('Y-m-d');
            }
        }
        return $extendedDueDate;
    }

    public static function calculateCommission(Payment $payment, Customer $customer): float
    {
        $commission = 0;
        if (self::isDynamicBilling()) {
            if ($payment->amount == $customer->package->price) {
                $commission = $customer->package->reseller_commission;
            } else {
                $percentage = self::calculatePercentage($customer->package->reseller_commission, $customer->package->price);
                $commission = self::calculateCommissionFromAmount($payment->amount, $percentage);
            }
        }
        return self::numberFormat($commission);
    }

    public static function calculatePercentage($commission_amount, $total_amount)
    {
        if ($commission_amount && $total_amount) {
            return self::numberFormat((($commission_amount / $total_amount) * 100));
        } else {
            return 0;
        }
    }

    public static function calculateCommissionFromAmount($amount, $percentage)
    {
        if ($amount && $percentage) {
            return self::numberFormat(($percentage / 100) * $amount);
        } else {
            return 0;
        }
    }

    public static function isDynamicBilling(): bool
    {
        return getOption('bill_type') == 'bi-monthly' && getOption('billing_cycle') == 1;
    }

    protected static function ceil($number): float
    {
        return ceil($number);
    }

    protected static function round($number): float
    {
        return round($number, 0);
    }

    protected static function floor($number): float
    {
        return floor($number);
    }

    public static function strtoln($string, $replace = ''): string
    {
        return str_replace(PHP_EOL, $replace, $string);
    }

    public static function dataTableButtons(array $buttons): array
    {
        $str = [];
        if (in_array('copy', $buttons)) {
            $str[] = [
                'extend' => 'copy',
                'text' => '<i class="fas fa-copy"></i> ',
                'titleAttr' => 'Copy to clipboard',
                'className' => 'btn btn-sm btn-default',
                'messageTop' => 'All Customers',
                'footer' => true,
                'exportOptions' => [
                    'columns' => [':visible']
                ]
            ];
        }

        if (in_array('pdf', $buttons)) {
            $str[] = [
                'extend' => 'pdf',
                'title' => "Customer list",
                'text' => '<i class="fas fa-file-pdf"></i> ',
                'titleAttr' => 'Export to PDF',
                'className' => 'btn btn-sm btn-success',
                'messageTop' => 'All Customers',
                'orientation' => 'landscape',
                'footer' => true,
                'exportOptions' => [
                    'columns' => [':visible']
                ]
            ];
        }

        if (in_array('print', $buttons)) {
            $str[] = [
                'extend' => 'print',
                'text' => '<i class="fa fa-print"></i> ',
                'titleAttr' => 'Print customer',
                'className' => 'btn btn-sm btn-info',
                'messageTop' => 'All Customers',
                'footer' => true,
                'exportOptions' => [
                    'columns' => [1, 2, ':visible']
                ]
            ];
        }

        if (in_array('visibility', $buttons)) {
            $str[] = [
                'extend' => 'colvis',
                'postfixButtons' => ['colvisRestore'],
                'className' => 'btn btn-sm btn-default'
            ];
        }
        return $str;
    }
}
