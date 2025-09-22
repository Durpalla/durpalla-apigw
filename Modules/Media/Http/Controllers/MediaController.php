<?php

namespace Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Media\Entities\Media;
use Modules\Media\Http\Requests\MediaCreateRequest;
use Modules\Media\Http\Requests\MediaUpdateRequest;
use Modules\Media\MediaService;
use Yajra\DataTables\Facades\DataTables;

class MediaController extends Controller
{
    use ValidatesRequests, AuthorizesRequests;
    private $medias;

    public function __construct(MediaService $medias)
    {
        parent::__construct();
        $this->medias = $medias;
    }

    public function index( Request $request)
    {
        if($request->wantsJson()) {
            $medias = Media::select(['id', 'attachment', 'type', 'size', 'dimension'])->orderByDesc('created_at');

            return Datatables::of($medias)
                ->addColumn('thumbnail', function($media) {
                    $thumb = ($media->attachment) ? $media->attachment : 'default/brand.jpg';
                    return "<img src='" . asset($thumb) . "' class='table-img' />";
                })
                ->addColumn('action', function($media) {
                    return "<form action='" . route('media.destroy', $media->id) . "' method='POST'>
                        ". csrf_field() . method_field('DELETE') ."
                        <button type='submit' class='btn btn-danger'>Delete</button>
</form>";
                })
                ->rawColumns(['action', 'thumbnail'])->addIndexColumn()
                ->make(true);
        }
        return $this->themedView('media::index');
    }

    public function create(): View
    {
        return $this->themedView('media::create');
    }

    public function store(MediaCreateRequest $request): JsonResponse
    {
        $data = ['status' => false, 'message' => 'Upload failed'];
        try {
            if (is_executable($request->file('file'))) {
                throw new \Exception('File not allowed to upload');
            }
            $media = $this->medias->handle($request->file('file'));
            $data['status'] = true;
            $data['file'] = $media->attachment;
        } catch (\Throwable $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    public function show(Media $media): View
    {
        return $this->themedView('media::show', compact('media'));
    }

    public function edit(Media $media): View
    {
        return $this->themedView('media::edit', compact('media'));
    }

    public function update(MediaUpdateRequest $request, $id): RedirectResponse
    {
        try {
            $this->medias->update($request->validated(), $id);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        return redirect()->route('media.index');
    }

    public function destroy(Media $media): RedirectResponse
    {
        if(!auth()->user()->can('media-delete'))
            session()->flash('error', 'You have no right to delete media');

        $media->delete();
        return redirect()->back();
    }

    public function jqUpload(Request $request): JsonResponse
    {
        $data = ['status' => false, 'message' => 'Could not upload'];
        try {
            $this->medias->handle($request->file('attachment'));
        } catch (\Throwable $exception) {
            $data['message'] = $exception->getMessage();
        }
        return response()->json($data);
    }

    public function callAction($method, $parameters)
    {
        if (!in_array($method, ['jqUpload'])) {
            $this->authorize($method, Media::class);
        }
        return parent::callAction($method, $parameters);
    }
}
