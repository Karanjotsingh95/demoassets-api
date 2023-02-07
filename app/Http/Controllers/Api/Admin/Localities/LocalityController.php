<?php

namespace App\Http\Controllers\Api\Admin\Localities;

use App\Http\Controllers\Controller;
use App\Models\Localities\ManufacturingSite;
use Illuminate\Http\Request;
use App\Models\Localities\Market;
use App\Models\Localities\Region;
use App\Models\Localities\RepairSite;
use App\Models\Localities\Territory;

class LocalityController extends Controller
{
    // Get all markets
    public function getMarkets()
    {
        return Market::orderBy('id', 'ASC')->with('regions')->with('regions.territories')->get();
    }

    // Get all Regions
    public function getRegions()
    {
        return Region::with('market')->get();
    }

    // Get all Territories
    public function getTerritories()
    {
        return Territory::with('region')->with('region.market')->get();
    }

    // Get a single market
    public function getMarket($id)
    {
        return Market::where('id', $id)->with('regions')->with('regions.territories')->first();
    }

    // Create a market
    public function createMarket(Request $request)
    {
        $market = Market::create([
            'market' => $request->market['market']
        ]);
        return $market;
    }

    // Update a market
    public function updateMarket($id, Request $request)
    {
        $market = Market::find($id);
        $market->market = $request->market['market'];
        $market->save();
        return $market;
    }

    // Delete a market
    public function deleteMarket($id, Request $request)
    {
        $market = Market::find($id);
        // Grab all the regions for this market
        $regions = Region::where('market_id', $market->id)->get();
        // Delete all territories that belong to these regions
        $region_ids = [];
        foreach ($regions as $r) {
            array_push($region_ids, $r->id);
        }
        Territory::whereIn('region_id', $region_ids)->delete();
        // Now delete the regions
        Region::where('market_id', $market->id)->delete();
        // Finally delete the market
        $market->delete();
        return $market;
    }

    // Get a single region
    public function getRegion($id)
    {
        return Region::where('id', $id)->with('territories')->first();
    }

    // Create a region
    public function createRegion(Request $request)
    {
        $region = Region::create([
            'market_id' => $request->region['market_id'],
            'region' => $request->region['region']
        ]);
        return $region;
    }

    // Update a region
    public function updateRegion($id, Request $request)
    {
        $region = Region::find($id);
        $region->region = $request->region['region'];
        $region->market_id = $request->region['market_id'];
        $region->save();
        return $region;
    }

    // Delete a region
    public function deleteRegion($id, Request $request)
    {
        $region = Region::find($id);
        // Delete all territories that belong to this region
        Territory::where('region_id', $region->id)->delete();
        // Now delete the region
        $region->delete();
        return $region;
    }

    // Create a territory
    public function createTerritory(Request $request)
    {
        $region = Territory::create([
            'region_id' => $request->territory['region_id'],
            'territory' => $request->territory['territory']
        ]);
        return $region;
    }

    // Delete a territory
    public function deleteTerritory($id, Request $request)
    {
        $territory = Territory::where('id', $id)->delete();
        return $territory;
    }

    // Get a territory
    public function getTerritory($id)
    {
        $territory = Territory::find($id);
        return $territory;
    }

    // Update a territory
    public function updateTerritory($id, Request $request)
    {
        $territory = Territory::find($id);
        $territory->territory = $request->territory['territory'];
        $territory->region_id = $request->territory['region_id'];
        $territory->save();
        return $territory;
    }

    // Get manufacturing sites
    public function getManufacturingSites() {
        $sites = ManufacturingSite::orderBy('manufacturing_site_name', 'ASC')->get();
        return $sites;
    }

    // Get manufacturing site
    public function getManufacturingSite($id) {
        $site = ManufacturingSite::find($id);
        return $site;
    }

    // Create a manufacturing site
    public function createManufacturingSite(Request $request) {
        $site = ManufacturingSite::create([
            'manufacturing_site_name' => $request->site['manufacturing_site_name'],
            'address' => $request->site['address'],
            'phone' => $request->site['phone'],
            'email' => $request->site['email']
        ]);
        return $site;
    }

    // Update a manufacturing site
    public function updateManufacturingSite($id, Request $request) {
        $site = ManufacturingSite::find($id);
        $site->manufacturing_site_name = $request->site['manufacturing_site_name'];
        $site->address = $request->site['address'];
        $site->phone = $request->site['phone'];
        $site->email = $request->site['email'];
        $site->save();
        return $site;
    }

    // Delete a manufacturing site
    public function deleteManufacturingSite($id) {
        $site = ManufacturingSite::where('id', $id)->delete();
        return $site;
    }

    // Get repair sites
    public function getRepairSites() {
        $sites = RepairSite::orderBy('repair_site_name', 'ASC')->get();
        return $sites;
    }

    // Get repair site
    public function getRepairSite($id) {
        $site = RepairSite::find($id);
        return $site;
    }

    // Create a repair site
    public function createRepairSite(Request $request) {
        $site = RepairSite::create([
            'repair_site_name' => $request->site['repair_site_name'],
            'address' => $request->site['address'],
            'phone' => $request->site['phone'],
            'email' => $request->site['email']
        ]);
        return $site;
    }

    // Update a repair site
    public function updateRepairSite($id, Request $request) {
        $site = RepairSite::find($id);
        $site->repair_site_name = $request->site['repair_site_name'];
        $site->address = $request->site['address'];
        $site->phone = $request->site['phone'];
        $site->email = $request->site['email'];
        $site->save();
        return $site;
    }

    // Delete a repair site
    public function deleteRepairSite($id) {
        $site = RepairSite::where('id', $id)->delete();
        return $site;
    }

    // Check if a site is already taken
    public function checkLocality(Request $request) {
        $localityExists = 0;
        if($request->type) {
            if($request->type == 'market') {
                if(Market::where('market', $request->string)->count() > 0) {
                    $localityExists = 1;
                }
            }elseif($request->type == 'region') {
                if(Region::where('market_id', $request->parent_id)->where('region', $request->string)->count() > 0) {
                    $localityExists = 1;
                }
            }elseif($request->type == 'territory') {
                if(Territory::where('region_id', $request->parent_id)->where('territory', $request->string)->count() > 0) {
                    $localityExists = 1;
                }
            }
        }
        return $localityExists;
    }
}
