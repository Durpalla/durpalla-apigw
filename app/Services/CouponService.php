<?php


namespace App\Services;


use App\Models\Coupon;
use App\Support\RasterImage;
use App\Repository\Interfaces\CouponRepositoryInterface;

class CouponService
{
    private $repository;
    private $calculation;

    public function __construct( CouponRepositoryInterface $couponRepository, CalculationService $calculation)
    {
        $this->repository = $couponRepository;
        $this->calculation = $calculation;
    }

    public function all()
    {
        return $this->repository->all();
    }

    public function create(array $data)
    {
        $poster = null;
        if (request()->hasFile('poster')) {
            $image = request()->file('poster');
            $filename = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/banner/');
            if (! is_dir($destinationPath)) {
                @mkdir($destinationPath, 0755, true);
            }
            $source = $image->getRealPath() ?: $image->getPathname();
            RasterImage::resizeToFit($source, $destinationPath . '/' . $filename, 460, 340);

            $poster = '/uploads/banner/' . $filename;
        }
        $coupon = $this->repository->create(array_merge($data, [
            'offer_start' => $this->calculation->createDate($data['offer_start']),
            'offer_end' => $this->calculation->createDate($data['offer_end']),
            'poster' => $poster,
            'items' => implode(",", $data['items']),
            'user_id' => auth()->user()->id
        ]));
//        if ($data['items'] && is_array($data['items'])) {
//            $couponMappings = [];
//
//            foreach ($data['items'] as $item) {
//                array_push($couponMappings, [
//                    'coupon_id' => $coupon->id,
//                    'type' => $coupon->type,
//                    'item_id' => $item
//                ]);
//            }
//
//            CouponMapping::insert($couponMappings);
//        }
        return $coupon;
    }

    public function update(array $data, $id)
    {
        return $this->repository->update($data, $id);
    }

    public function getOffers()
    {
        $offers = Coupon::select('poster')->where('is_offer', 1)->take(6)->get();
        // ->where('poster', '!=', null)
        return $offers->map(function ($item) {
            $item->thumbnail = upload_asset($item->poster);
            $item->poster = upload_asset($item->poster);
            return $item;
        })->toArray();
    }
}
