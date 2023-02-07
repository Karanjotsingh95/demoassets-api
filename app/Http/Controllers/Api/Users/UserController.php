<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Localities\Market;
use App\Models\Localities\Region;
use App\Models\Localities\Territory;
use App\Models\Users\ColumnFilter;
use App\Models\Users\Address;

class UserController extends Controller
{
    // Grab all users
    public function getAllUsers(Request $request) {
        $users = User::orderBy('last_name', 'ASC')->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->where('active', $request->active)->get();
        return $users;
    }

    // Grab all users
    public function getAllInactiveUsers() {
        $users = User::orderBy('last_name', 'ASC')->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->where('active', 0)->get();
        return $users;
    }

    // Grab the logged in user
    public function getLoggedInUser(Request $request) {
        $user = User::where('id', $request->user()->id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Grab a single user
    public function getUser($id, Request $request) {
        $user = User::where('id', $id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Create a user
    public function createUser(Request $request) {
        $user = User::create([
            'first_name' => $request->user['first_name'],
            'last_name' => $request->user['last_name'],
            'email' => $request->user['email'],
            'password' => bcrypt($request->user['password']),
            'admin' => $request->user['admin'],
            'title' => $request->user['title'],
            'manager' => $request->user['manager'],
            'mobile' => $request->user['mobile'],
            'office' => $request->user['office'],
            'address' => $request->user['address'],
            'company' => $request->user['company'],
            'profile_image' => $request->user['profile_image'],
            'market_id' => $request->user['market_id'],
            'region_id' => $request->user['region_id'],
            'territory_id' => $request->user['territory_id'],
            'company_address' => $request->user['company_address'],
            'active' => $request->user['active'],
            'demo_coordinator' => $request->user['demo_coordinator']
        ]);

        // Add addresses to the user
        foreach($request->user['addresses'] as $add) {
            Address::create([
                'user_id' => $user->id,
                'address' => $add['address'],
                'primary' => $add['primary']
            ]);
        }

        $filters = json_encode([
            'name',
            'email',
            'administrator',
            'manager',
            'department'
        ]);
        // Add column filters!
        ColumnFilter::create([
            'user_id' => $user->id,
            'type' => 'users',
            'columns' => $filters
        ]);
        $filters = json_encode([
            'asset_title',
            'status'
        ]);
        ColumnFilter::create([
            'user_id' => $user->id,
            'type' => 'assets',
            'columns' => $filters
        ]);
        $filters = json_encode([
            'asset_title',
            'status'
        ]);
        ColumnFilter::create([
            'user_id' => $user->id,
            'type' => 'unique_assets',
            'columns' => $filters
        ]);
        $filters = json_encode([
            'asset_title',
            'status'
        ]);
        ColumnFilter::create([
            'user_id' => $user->id,
            'type' => 'accessories',
            'columns' => $filters
        ]);

        if($request->user['send_welcome_email'] == 1) {
            // Send an email
            $customMessage = array(
                'headline' => 'Your Megger Account is ready.',
                'message' => '<p>You may log in with the following credentials:<br>Email: '.$user->email.'<br>Password: '.$request->user['password'].'</p>',
                'emailTo' => $user->email,
                'subject' => 'Your Megger Account is ready.',
                'button' => true,
                'buttonText' => 'login',
                'buttonLink' => env('FRONTEND_URL').'/login'
            );

            $this->transactionalEmail($customMessage);
        }

        return $user;
        
    }

    // Update a user
    public function updateUser($id, Request $request) {
        $user = User::where('id', $id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        $user->first_name = $request->user['first_name'];
        $user->last_name = $request->user['last_name'];
        $user->email = $request->user['email'];
        $user->demo_coordinator = $request->user['demo_coordinator'];
        if(isset($request->user['password'])) {
            $user->password = bcrypt($request->user['password']);
            if($request->user['sendPasswordEmail']) {
                // Send an email
                $customMessage = array(
                    'headline' => 'Your Megger Account Was Updated.',
                    'message' => '<p>Your account password has been updated to:<br><div style="border:2px solid white;padding:50px;font-size:30px;margin:40px 0px;">'.$request->user['password'].'</div></p>',
                    'emailTo' => $user->email,
                    'subject' => 'Your Megger Account Was Updated.',
                    'button' => true,
                    'buttonText' => 'login',
                    'buttonLink' => env('FRONTEND_URL').'/login'
                );

                $this->transactionalEmail($customMessage);
            }
        }
        $user->admin = $request->user['admin'];
        $user->title = $request->user['title'];
        $user->manager = $request->user['manager'];
        $user->mobile = $request->user['mobile'];
        $user->office = $request->user['office'];
        $user->address = $request->user['address'];
        $user->company = $request->user['company'];
        $user->profile_image = $request->user['profile_image'];
        $user->market_id = $request->user['market_id'];
        $user->region_id = $request->user['region_id'];
        $user->territory_id = $request->user['territory_id'];
        $user->company_address = $request->user['company_address'];
        $user->active = $request->user['active'];
        $user->save();

        return $user;
        
    }

    // Delete a user
    public function deleteUser($id) {
        $user = User::where('id', $id)->delete();
        // Delete column filters
        ColumnFilter::where('user_id', $id)->delete();
        return $user;
    }

    // Check for a duplicate email
    public function checkDupEmail(Request $request) {
        $user = User::where('email', $request->email)->first();
        if($user) {
            return 1;
        }else {
            return 0;
        }
    }

    // Import Users
    public function importUsers(Request $request) {

        $data = json_decode($request->data, true);

        $i = 0;

        $rows = count($data[0]['data']);

        while($i <= $rows) {

            $email = '';
            if(isset($data[0]['data'][$i])) {
                $email = $data[0]['data'][$i];
            }

            $first_name = '';
            if(isset($data[1]['data'][$i])) {
                $first_name = $data[1]['data'][$i];
            }

            $last_name = '';
            if(isset($data[2]['data'][$i])) {
                $last_name = $data[2]['data'][$i];
            }

            $password = '';
            if(isset($data[3]['data'][$i])) {
                $password = bcrypt($data[3]['data'][$i]);
            }

            $admin = '';
            if(isset($data[4]['data'][$i])) {
                $admin = $data[4]['data'][$i];
            }

            $title = '';
            if(isset($data[5]['data'][$i])) {
                $title = $data[5]['data'][$i];
            }

            $manager = '';
            if(isset($data[6]['data'][$i])) {
                $manager = $data[6]['data'][$i];
            }

            $mobile = '';
            if(isset($data[7]['data'][$i])) {
                $mobile = $data[7]['data'][$i];
            }

            $office = '';
            if(isset($data[8]['data'][$i])) {
                $office = $data[8]['data'][$i];
            }

            $company = '';
            if(isset($data[9]['data'][$i])) {
                $company = $data[9]['data'][$i];
            }

            $company_address = '';
            if(isset($data[10]['data'][$i])) {
                $company_address = $data[10]['data'][$i];
            }

            $active = '';
            if(isset($data[15]['data'][$i])) {
                $active = $data[15]['data'][$i];
            }

            $demo_coordinator = 0;
            if(isset($data[16]['data'][$i])) {
                $demo_coordinator = $data[16]['data'][$i];
            }

            if($email && $first_name && $last_name && $password) {
                // Does this email exist?
                if(User::where('email', $email)->count() == 0) {
                    $user = User::create([
                        'email' => $email,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'password' => $password,
                        'admin' => $admin,
                        'title' => $title,
                        'manager' => $manager,
                        'mobile' => $mobile,
                        'office' => $office,
                        'company' => $company,
                        'company_address' => $company_address,
                        'active' => $active,
                        'demo_coordinator' => $demo_coordinator
                    ]);

                    // Add the market
                    if(isset($data[11]['data'][$i])) {
                        if(Market::where('market', 'LIKE', '%'.$data[11]['data'][$i].'%')->count() > 0) {
                            $mark = Market::where('market', 'LIKE', '%'.$data[11]['data'][$i].'%')->first();
                            $user->market_id = $mark->id;
                            $user->save();
                        }
                    }

                    // Add the region
                    if(isset($data[12]['data'][$i])) {
                        if(Region::where('region', 'LIKE', '%'.$data[12]['data'][$i].'%')->count() > 0) {
                            $reg = Region::where('region', 'LIKE', '%'.$data[12]['data'][$i].'%')->first();
                            $user->region_id = $reg->id;
                            $user->save();
                        }
                    }

                    // Add the territory
                    if(isset($data[13]['data'][$i])) {
                        if(Territory::where('territory', 'LIKE', '%'.$data[13]['data'][$i].'%')->count() > 0) {
                            $ter = Territory::where('territory', 'LIKE', '%'.$data[13]['data'][$i].'%')->first();
                            $user->territory_id = $ter->id;
                            $user->save();
                        }
                    }

                    // Add addresses
                    $i = 0;
                    if(isset($data[14]['data'][$i])) {
                        $add = explode("|", $data[14]['data'][$i]);
                        foreach($add as $key => $value) {
                            $primary = 0;
                            if($i == 0) {
                                $a = Address::where('user_id', $user->id)->get();
                                foreach($a as $a) {
                                    $a->primary = 0;
                                    $a->save();
                                }
                                $primary = 1;
                            }
                            Address::create([
                                'user_id' => $user->id,
                                'address' => $value,
                                'primary' => $primary
                            ]);
                            $i++;
                        }
                    }

                    $filters = json_encode([
                        'name',
                        'email',
                        'administrator',
                        'manager',
                        'department'
                    ]);
                    // Add column filters!
                    ColumnFilter::create([
                        'user_id' => $user->id,
                        'type' => 'users',
                        'columns' => $filters
                    ]);
                    $filters = json_encode([
                        'asset_title',
                        'status'
                    ]);
                    ColumnFilter::create([
                        'user_id' => $user->id,
                        'type' => 'assets',
                        'columns' => $filters
                    ]);
                    $filters = json_encode([
                        'asset_title',
                        'status'
                    ]);
                    ColumnFilter::create([
                        'user_id' => $user->id,
                        'type' => 'unique_assets',
                        'columns' => $filters
                    ]);
                    $filters = json_encode([
                        'asset_title',
                        'status'
                    ]);
                    ColumnFilter::create([
                        'user_id' => $user->id,
                        'type' => 'accessories',
                        'columns' => $filters
                    ]);
                }
            }

            $i++;
        }
        return 'success';
    }

    // Save Column Filters
    public function saveFilters(Request $request) {
        $user = User::find($request->user()->id);
        $filter = ColumnFilter::where('user_id', $user->id)->where('type', $request->type)->first();
        if($filter) {
            $filter->columns = json_encode($request->columns);
            $filter->save();
        }
        // Return a fresh user
        return $this->getLoggedInUser($request);
    }

    // TEST DELETE
    public function test() {
        // Email the demo coordinators!
        $admins = User::where('region_id', 1)->where('manager', 1)->get();
        return $admins;
    }

    // Add an address
    public function saveAddress(Request $request) {
        if($request->address) {
            $user = User::find($request->user()->id);
            // Add the address
            $newAddress = Address::create([
                'user_id' => $user->id,
                'address' => $request->address
            ]);
        }

        // Send back a fresh user copy
        $user = User::where('id', $request->user()->id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Add an address
    public function saveAddressAdmin(Request $request) {
        if($request->address) {
            $user = User::find($request->user_id);
            // Add the address
            $newAddress = Address::create([
                'user_id' => $user->id,
                'address' => $request->address
            ]);
        }

        // Send back a fresh user copy
        $user = User::where('id', $request->user_id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Make an address primary
    public function makeAddressPrimary(Request $request) {
        $user = User::find($request->user()->id);
        // Does this address belong to the user?
        if(Address::where('id', $request->id)->where('user_id', $user->id)->count() > 0) {
            // it is!
            // Mark all other addresses not primary
            $addresses = Address::where('user_id', $user->id)->get();
            foreach($addresses as $address) {
                $address->primary = 0;
                $address->save();
            }
            // Grab this address
            $address = Address::find($request->id);
            // Make it primary
            $address->primary = 1;
            $address->save();
        }

        // Send back a fresh user copy
        $user = User::where('id', $request->user()->id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Make an address primary
    public function makeAddressPrimaryAdmin(Request $request) {
        $user = User::find($request->user_id);
        // Does this address belong to the user?
        if(Address::where('id', $request->id)->where('user_id', $user->id)->count() > 0) {
            // it is!
            // Mark all other addresses not primary
            $addresses = Address::where('user_id', $user->id)->get();
            foreach($addresses as $address) {
                $address->primary = 0;
                $address->save();
            }
            // Grab this address
            $address = Address::find($request->id);
            // Make it primary
            $address->primary = 1;
            $address->save();
        }

        // Send back a fresh user copy
        $user = User::where('id', $request->user_id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // delete an address
    public function deleteAddress($id, Request $request) {
        $user = User::find($request->user()->id);
        // Does this address belong to the user?
        if(Address::where('id', $id)->where('user_id', $user->id)->count() > 0) {
            // it is!
            // Delete the address
            Address::where('user_id', $user->id)->where('id', $id)->delete();
        }

        // Send back a fresh user copy
        $user = User::where('id', $request->user()->id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // delete an address
    public function deleteAddressAdmin($user_id, $id, Request $request) {
        $user = User::find($user_id);
        // Does this address belong to the user?
        if(Address::where('id', $id)->where('user_id', $user->id)->count() > 0) {
            // it is!
            // Delete the address
            Address::where('user_id', $user->id)->where('id', $id)->delete();
        }

        // Send back a fresh user copy
        $user = User::where('id', $user_id)->with('market')->with('region')->with('territory')->with('columnFilters')->with('addresses')->with('primaryAddress')->first();
        return $user;
    }

    // Check an address
    public function checkAddress(Request $request) {
        $exists = 0;
        if(Address::where('user_id', $request->userId)->where('address', $request->address)->count() > 0) {
            $exists = 1;
        }
        return $exists;
    }
}