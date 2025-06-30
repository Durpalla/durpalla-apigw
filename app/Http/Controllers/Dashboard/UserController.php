<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Designation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UserCreateRequest;
use App\Services\RoleService;
use App\Services\UserService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Image;

class UserController extends Controller
{
    protected $success = 200;
    protected $roleService;
    protected $user;

    public function __construct(RoleService $roleService, UserService $userService)
    {
        $this->roleService = $roleService;
        $this->user = $userService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $user = Auth::user();
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = User::with(['roles', 'counter'])->where('type', $user->type);

            //deselect self from query
            // $query->where('id', '!=', $user->id);

            if ($user->type == 'merchant') {
                if ($user->hasRole('merchant')) {
                    $query->where('merchant_id', $user->id);
                } else {
                    $query->where('merchant_id', $user->merchant_id);
                }
            }

            //filter by keyword
            if (isset($_GET['keyword']) && $_GET['keyword'] != null) {
                $keyword = $_GET['keyword'];
                $query->where(function ($q) use ($keyword) {
                    $q->orWhereRaw("lower(id) LIKE '%" . strtolower($keyword) . "%'");
                    $q->orWhereRaw("lower(email) LIKE '%" . strtolower($keyword) . "%'");
                    $q->orWhereRaw("lower(mobile) LIKE '%" . strtolower($keyword) . "%'");
                    $q->orWhereRaw("lower(name) LIKE '%" . strtolower($keyword) . "%'");
                    // $q->orWhereRaw("lower(username) LIKE '%" . strtolower($keyword) . "%'");
                });
            }

            // //filter by status
            if (isset($_GET['status']) && $_GET['status'] != null) {
                $status = (int)$_GET['status'];
                $query->where('status', $status);
            }

            //filter by role
            if (isset($_GET['role']) && $_GET['role'] != null) {
                $role = $_GET['role'];
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('id', $role);
                });
            }

            $total = $query->count();

            $query->offset($start);
            $query->limit($limit);
            $query->orderBy($column, $order);
            $users = $query->get();

            //sanitize data
            $returnArr = array();
            if ($users) {
                foreach ($users as $user) {
                    $row['id'] = $user->id;
                    $row['name'] = $user->name;
                    // $row['username'] = $user->username;
                    $row['mobile'] = $user->mobile;
                    $row['email'] = $user->email;
                    $row['role'] = $user->roles[0];
                    $row['counter'] = ($user->counter != null) ? $user->counter['name'] : 'N/A';
                    $row['photo'] = ($user->profile_pic) ? asset($user->profile_pic) : asset('default/avatar.png');
                    $row['status'] = ($user->email_verified_at !== null) ? 'Active' : (($user->deleted_at === null) ? 'Pending' : 'Deleted');
                    array_push($returnArr, $row);
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json($data);
        }

        $roles = Role::whereNotIn('name', ['customer'])->where('type', 'admin')->pluck('name', 'id');

        return view('admin.user.index', compact('roles'))->withTitle('Users');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $roles = $this->roleService->getRoles();
        $designations = Designation::pluck('name', 'id');
        return view('admin.user.create', compact('roles', 'designations'))->withTitle('Add new user');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return Response
     */
    public function store(UserCreateRequest $request)
    {
        try {
            DB::transaction(function() use($request){
                $user = $this->user->create($request->validated());
            }, 2);
            return redirect()->route('dashboard.user.index')->withMessage([
                'label' => 'success',
                'content' => 'User has been successfully created'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withMessage([
                'label' => 'error',
                'content' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param User $user
     * @return Response
     */
    public function show(User $user)
    {
        return view('admin.user.show', compact('user'))->withTitle('User : ' . $user->name);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function profile()
    {
        $user = Auth::user();
        return view('admin.user.profile', compact('user'))->withTitle('My profile');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = $this->roleService->getRoles();
        $designations = Designation::pluck('name', 'id');
        return view('admin.user.edit', compact('user', 'roles', 'designations'))->withTitle('Edit user');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot create user'];
        //form validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|max:191',
            'email' => 'bail|required|unique:users,email,' . $id,
            'designation_id' => 'bail|required|exists:designations,id',
            // 'username' => 'bail|required|unique:users,username,' . $id,
            'mobile' => 'bail|required|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile,' . $id,
            'password' => 'bail|nullable|same:password_confirm|min:8|max:32',
            'role' => 'bail|required|integer|exists:roles,id',
            'avatar' => 'bail|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=100,min_height=100'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            // dd( $request->all());
            //Saving data
            DB::beginTransaction(); //Start transaction!

            try {
                //assign role
                $userRole = Role::where('id', $request->role)->first();
                //saving logic here
                $user = User::with(['roles'])->findOrFail($id);
                $user->name = $request->name;
                // $user->username = $request->username;
                // $user->gender = $request->gender;
                $user->designation_id = $request->designation_id;
                $user->email = $request->email;
                $user->mobile = $request->mobile;
                $user->type = $userRole->type;
                $user->password = ($request->password) ? Hash::make($request->password) : $user->password;
                //upload poster/banner
                if ($request->file('avatar')) {
                    $image = $request->file('avatar');
                    $filename = time() . '.' . $image->getClientOriginalExtension();
                    $destinationPath = public_path('/avatars');
                    $img = Image::make($image->getRealPath());
                    $img->resize(460, 340, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save($destinationPath . '/' . $filename);
                    $user->profile_pic = 'avatars/' . $filename;
                }
                //if merchant user
                if (Auth::user()->type == 'merchant') {
                    if (Auth::user()->hasRole('merchant')) {
                        $user->merchant_id = Auth::user()->id;
                    } else {
                        $user->merchant_id = Auth::user()->merchant_id;
                    }
                }
                if ($user->save()) {
                    //remove previous role
                    if ($user->hasanyrole(Role::all())) {
                        foreach ($user->roles as $role) {
                            $user->removeRole($role);
                        }
                    }
                    $user->assignRole($userRole);
                }
                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'User cannot updated';
            } catch (\Exception $e) {
                //failed logic here
                DB::rollback();
                $data['content'] = $e->getMessage();
            }
        }

        if ($data['status'] == true) {
            return redirect()->route('dashboard.user.index')->with([
                'message' => $data
            ]);
        } else {
            return redirect()->back()->with([
                'message' => $data
            ])->withInput($request->all())->withErrors($validator->errors());
        }
    }

    public function action(Request $request)
    {
        $customer_id = $request->id;
        if (isset($request->action)) {
            call_user_func(array($this, $request->action), $request);
        }
    }

    private function active($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot activate'];
        $user = User::findOrFail($request->id);
        $user->status = 1;
        $user->email_verified_at = now();
        if ($user->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'User has been successfully activated';
        }

        if ($request->ajax() === True) {
            echo json_encode($data);
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function delete($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot delete'];
        $user = User::findOrFail($request->id);
        if ($user->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'User has been successfully deleted';
        }

        // dd( $data );

        if ($request->ajax() === True) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot change password'];
        $validator = Validator::make($request->all(), [
            'old_password' => 'bail|required|string',
            'password' => 'bail|required|string|min:8|max:20',
            'confirm_password' => 'bail|required|string|same:password',
        ]);

        if ($validator->fails() == true) {
            $data['content'] = $validator->errors()->first();
        } else {
            if (Hash::check($request->old_password, Auth::user()->password)) {
                $user = Auth::user();
                $user->password = Hash::make($request->password);
                if ($user->save()) {
                    $data['content'] = 'You have successfully change password';
                    $data['status'] = true;
                    $data['label'] = 'success';
                }
            } else {
                $data['content'] = 'Your old password does not match';
            }
        }

        if ($request->ajax() === True) {
            return response()->json($data);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function upload(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot upload profile picture'];
        //form validation rules
        $validator = Validator::make($request->all(), [
            'avatar' => 'bail|nullable|image|mimes:png,jpg,jpeg|max:2048|dimensions:min_width=100,min_height=100'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            try {
                DB::transaction(function () use ($request, &$data) {
                    //saving logic here
                    $user = Auth::user();
                    if ($request->file('avatar')) {
                        $image = $request->file('avatar');
                        $filename = time() . '.' . $image->getClientOriginalExtension();
                        $destinationPath = public_path('/avatars');
                        $img = Image::make($image->getRealPath());
                        $img->resize(460, 340, function ($constraint) {
                            $constraint->aspectRatio();
                        })->save($destinationPath . '/' . $filename);
                        $user->profile_pic = 'avatars/' . $filename;
                    }
                    $user->save();
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Your profile picture successfully uploaded';
                });
            } catch (\Exception $e) {
                $data['content'] = $e->getMessage();
            }
        }

        if ($request->ajax() == true) {
            return response()->json($data);
        } else {
            return redirect()->route('dashboard.user.profile', ['tab' => 'upload'])->with([
                'message' => $data
            ])->withErrors($validator->errors());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy(User $user)
    {
        if (Auth::user()->can('user-delete')) {
            $user->delete();

            return redirect()->back()->with([
                'message' => [
                    'label' => 'success',
                    'content' => 'User has been successfully deleted.'
                ]
            ]);
        } else {
            return redirect()->back()->with([
                'message' => [
                    'label' => 'danger',
                    'content' => 'You have no permission to delete user.'
                ]
            ]);
        }
    }

    /**
     * Logout the user from session.
     *
     * @return Response
     */
    public function logout()
    {
        Auth::logout();
        return redirect(route('login'))->with([
            'message' => [
                'label' => 'success',
                'content' => 'You are successfully loggedout.'
            ]
        ]);;
    }

    public function export()
    {

    }
}
