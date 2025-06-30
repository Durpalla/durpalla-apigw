<?php


namespace Modules\BroadCast;


use Illuminate\Http\JsonResponse;
use Intervention\Image\Facades\Image;
use App\Services\DashboardService;
use Modules\BroadCast\Entities\BroadCast;
use Modules\BroadCast\Repository\BroadcastRepositoryInterface;

class BroadcastService
{
    /**
     * @var BroadcastRepositoryInterface
     */
    public $broadcast;

    public function __construct(BroadcastRepositoryInterface $broadcastRepository)
    {
        $this->broadcast = $broadcastRepository;
    }

    public function create(array $data)
    {
        $data = $this->buildData($data);
        return $this->broadcast->create($data + ['user_id' => auth()->user()->id]);
    }

    public function update(array $data, $id)
    {
        $data = $this->buildData($data);
        return $this->broadcast->update($data, $id);
    }

    public function buildData(&$data)
    {
        if (request()->hasFile('attachment')) {
            $image = request()->file('attachment');
            $filename = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/broadcast/');
            $img = Image::make($image->getRealPath());
            $img->resize(460, 340, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath . '/' . $filename);

            $data['attachment'] = 'uploads/broadcast/' . $filename;
        }

        if ($data['group'] === 'individual' && request('customers') && is_array(request('customers'))) {
            $data['customers'] = implode(',', request('customers'));
        }

        return $data;
    }

    public function getDataTable(): JsonResponse
    {
        $query = BroadCast::query();
        $total = $query->count();
        $messages = $query->take(15)->latest()->get();
        return response()->json([
            'draw' => request()->get('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $messages->map(function ($item, $key) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->type,
                    'group' => $item->group,
                    'message' => $item->message,
                    'user' =>  $item->user ? $item->user->name : null,
                    'scheduled_at' => $item->scheduled_at
                ];
            })->toArray()
        ]);
    }
}
