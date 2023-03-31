<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Feed;

class FeedController extends Controller
{
    private $target_crude_protein_for_growing = 12;
    private $target_crude_protein_for_others = 14;
    private $lower;
    private $higher;
    private $step;

    public function __construct() {
        $this->lower = null;
        $this->higer = null;
        $this->step = 5;
        $this->interation = 2;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Feed::all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function IngredientMeetRequirement( $target_crude_protein, ...$ingredients ){
        $ingredients = array_filter($ingredients, function($key) use ($ingredients) {
            return isset($ingredients[$key]['name']);// == null;
        }, ARRAY_FILTER_USE_KEY);
       $ingredients = array_values($ingredients);
       $ing_crude_protein = array_column( $ingredients, 'crude_protein' );
       if( min( $ing_crude_protein ) < $target_crude_protein && max( $ing_crude_protein ) > $target_crude_protein )
            return true;
        abort('400', "At least 1 Ingredient must be less and Greater than Target Crude Protein ($target_crude_protein)");
    }

    public function formulateIngredient( $quantity, $target_crude_protein, ...$ingredients ) {
        $ing_crude_protein = array_column( $ingredients, 'crude_protein' );

        $lower_ing = min( $ing_crude_protein );
        $lower_name =  $ingredients[ array_search( $lower_ing, $ingredients ) ]['name'];

        $higher_ing = max( $ing_crude_protein );
        $higher_name =  $ingredients[ array_search( $higher_ing, $ingredients ) ]['name'];

        $lower = $target_crude_protein - $lower_ing;
        $higher = $higher_ing - $target_crude_protein;

        $result = $lower + $higher;
        $lower_percentage = ( $higher / $result ) * 100;
        $higher_percentage = ( $lower / $result ) * 100;

        return [
            'lower_ing' => $lower_ing, 
            'higher_ing' => $higher_ing, 
            'ingredients' => $ingredients, 
            'lower_percentage' => $lower_percentage, 
            'higher_percentage' => $higher_percentage, 
            'quantity' => $quantity
        ];
    }

    public function scaledown($lower_ing, $higher_ing, $ingredients, $lower_percentage, $higher_percentage, $quantity ) {
        //Scale down to within quantity supplied
        $ing_crude_protein = array_column( $ingredients, 'crude_protein' );
        $ingredients[ array_search( $lower_ing, $ing_crude_protein ) ]['quantity_required'] = number_format( ( $lower_percentage * $quantity ) / 100, 2 );
        $ingredients[ array_search( $higher_ing, $ing_crude_protein ) ]['quantity_required'] = number_format( ( $higher_percentage * $quantity ) / 100 , 2 );
        return $ingredients;
    }

    public function groupIngredient( $target_crude_protein, $ingredients ) {
        $groupIngredients = [];
        foreach( $ingredients as $ing ) {
            if( $ing['crude_protein'] <= $target_crude_protein )
                $groupIngredients['lower'][] = $ing;
            else
                $groupIngredients['higher'][] = $ing;
        }
        return $groupIngredients;
    }

    public function check( $ingredients ) {
        foreach( $ingredients as $key => $ing ) {
            $value = $ing['quantity'] - $ing['quantity_required'];
            $name = $ing['name'];
            if( $value < 0 ) {
                $format = -1 * number_format( $value, 2);
                $ingredients[$key]['message'] = "Get $format kg more of $name";
                $ingredients[$key]['meet_requirement'] = false;
            }else {
                $ingredients[$key]['meet_requirement'] = true;
            }
        }

        return $ingredients;
    }

    public function assignPercentage( $group, $subgrp ) {

        $quantities = array_column( $group, 'quantity');
        $quantity_sum = array_sum ( $quantities );
       
        foreach( $group as $key => $grp ) {
            if( $subgrp == null )
                $group[$key]['percentage'] = ( $grp['quantity'] / $quantity_sum ) * 100;
            else {
                $index = array_search( max( $quantities ), $quantities );
                if( $key == $index ) 
                    $group[$key]['percentage'] = $subgrp[$key]['percentage'] + $this->step;
                else
                    $group[$key]['percentage'] = $subgrp[$key]['percentage'] - $this->step;
            }
            $group[$key]['old_crude_protein'] = $group[$key]['crude_protein'];
            $group[$key]['crude_protein'] = ( $group[$key]['percentage'] * $group[$key]['old_crude_protein'] ) / 100;
        }
        return $group;
    }
    public function getNewCrudeProteinValue( &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
       
        $higher_grp = $groupIngredients['higher'];
        $groupIngredients['lower'] = $this->assignPercentage( $lower_grp, $this->lower );
        $groupIngredients['higher'] = $this->assignPercentage( $higher_grp, $this->higher );            
        return $groupIngredients;
    }

    public function addIngredientInGrp( &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        $groupIngredients['sum_lower_grp_crude_protein'] = array_sum( array_column( $lower_grp, 'crude_protein' ) );
        $groupIngredients['sum_higher_grp_crude_protein'] = array_sum( array_column( $higher_grp, 'crude_protein' ) );
        return $groupIngredients;
    }


    public function CalculateOtherParam( $rawdata, $data ) {
        //Crude Protein
        $cp = $dm = $ash = $ee = $nf = 0;
        foreach( $rawdata['ingredients'] as $d ) {
            //lower
            if( $d['crude_protein'] == $rawdata['lower_ing'] ) {
                $cp += ( $rawdata['lower_percentage'] * $d['crude_protein'] ) / 100;
                $dm += ( $rawdata['lower_percentage'] * $d['dry_matter'] ) / 100;
                $ash += ( $rawdata['lower_percentage'] * $d['ash'] ) / 100;
                $ee +=  ( $rawdata['lower_percentage'] * $d['ether_extract'] ) / 100;
                $nf +=  ( $rawdata['lower_percentage'] * $d['nfe'] ) / 100;
            }
            //higher
            if( $d['crude_protein'] == $rawdata['higher_ing'] ) {
                $cp += ( $rawdata['higher_percentage'] * $d['crude_protein'] ) / 100;
                $dm += ( $rawdata['higher_percentage'] * $d['dry_matter'] ) / 100;
                $ash += ( $rawdata['higher_percentage'] * $d['ash'] ) / 100;
                $ee +=  ( $rawdata['higher_percentage'] * $d['ether_extract'] ) / 100;
                $nf +=  ( $rawdata['higher_percentage'] * $d['nfe'] ) / 100;
            }
        }
        return [ 'data' => $data, 'crude_protein' => number_format($cp,2), 'dry_matter' => number_format($dm,2), 'ash' => number_format($ash,2), 'ether_extract' => number_format($ee,2), 'nfe' => number_format($nf, 2) ];
    }

    public function CalculateOtherParamMoreThanTwo( $data ) {
        $cp = $dm = $ash = $ee = $nf = 0;
        foreach( $data as $d ) {
            $cp += ( $d['percentage'] * $d['old_crude_protein'] ) / 100;
            $dm += ( $d['percentage'] * $d['dry_matter'] ) / 100;
            $ash += ( $d['percentage'] * $d['ash'] ) / 100;
            $ee +=  ( $d['percentage'] * $d['ether_extract'] ) / 100;
            $nf +=  ( $d['percentage'] * $d['nfe'] ) / 100;
        }
        return [ 'data' => $data, 'crude_protein' => number_format($cp,2), 'dry_matter' => number_format($dm,2), 'ash' => number_format($ash,2), 'ether_extract' => number_format($ee,2), 'nfe' => number_format($nf, 2) ];

    }

    public function aggregateData( $data ) {
        $all = [];
        foreach( $data['group']['lower'] as $d ) 
            $all[] = $d;
        foreach( $data['group']['higher'] as $d ) 
            $all[] = $d;
        return $all;
    }

    public function prediction( $data ) {
        if( request()->class == 'growing' && request()->animal_type == 'Goat'  )
            $data['prediction'] = 28.44 + ( 0.067 * $data['dry_matter'] ) - ( 0.16 * $data['crude_protein'] ) + ( 0.6 * $data['ash'] ) - ( 0.21 * $data['ether_extract'] ) - ( 0.84 * request()->body_weight ?? 1 );
        else if ( request()->class == 'growing' && request()->animal_type == 'Sheep' )
            $data['prediction'] = 20.80 + 0.15406 * $data['dry_matter'] + ( 0.234 * $data['ash'] ) - ( 0.91 * $data['ether_extract'] ) - ( 0.071 * $data['nfe'] ) - ( 1.14 * request()->body_weight ?? 1 ) + ( 1.98 * request()->age ?? 1 );
        $data['prediction'] = number_format( $data['prediction'], 2 );
        return $data;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $ingredient_number = $request->ingredient_number; // 2 to 5
        $animal_type = $request->animal_type; //Goat or Sheep
        $class = $request->class; // preg/growing/lactating/finishing
        $quantity = $request->quantity;
        $ingredient1 =  $request->ingredient1;
        $ingredient2 =  $request->ingredient2;
        $ingredient3 =  $request->ingredient3 ?? null;
        $ingredient4 =  $request->ingredient4 ?? null;
        $ingredient5 =  $request->ingredient5 ?? null;
        $target_crude_protein = $request->class == 'Growing' ? $this->target_crude_protein_for_growing : $this->target_crude_protein_for_others;
        if( $ingredient_number == 2 ) {
            // check if meet requirements
            $this->IngredientMeetRequirement( $target_crude_protein, $ingredient1, $ingredient2 );
            [
                'lower_ing' => $lower_ing, 
                'higher_ing' => $higher_ing, 
                'ingredients' => $ingredients, 
                'lower_percentage' => $lower_percentage, 
                'higher_percentage' => $higher_percentage, 
                'quantity' => $quantity
            ] = $this->formulateIngredient( $quantity, $target_crude_protein, $ingredient1, $ingredient2 );
            $ingredients = $this->scaledown(   $lower_ing, $higher_ing, 
                                $ingredients, $lower_percentage, 
                                $higher_percentage, $quantity );
            $data = $this->check( $ingredients );
            $rawdata = [
                'lower_ing' => $lower_ing, 
                'higher_ing' => $higher_ing, 
                'ingredients' => $ingredients, 
                'lower_percentage' => $lower_percentage, 
                'higher_percentage' => $higher_percentage, 
                'quantity' => $quantity
            ];
            //return $rawdata;
            return $this->CalculateOtherParam( $rawdata, $data );


        }else {
            $ingredients = [ $ingredient1, $ingredient2, $ingredient3, $ingredient4, $ingredient5 ];
            $data = $this->feedformulationForThreeOrMore( $target_crude_protein, $ingredients, $ingredient1, $ingredient2, $ingredient3, $ingredient4, $ingredient5, $quantity  );
            $data = $this->aggregateData( $data );
            $data = $this->CalculateOtherParamMoreThanTwo( $data );
            return $data;// $this->prediction( $data );
        }
            

    }

    public function feedformulationForThreeOrMore( $target_crude_protein, $ingredients, $ingredient1, $ingredient2, $ingredient3, $ingredient4, $ingredient5, $quantity  ) {
            $ingredients = array_filter($ingredients, function($key) use ($ingredients) {
                return isset($ingredients[$key]['name']);// == null;
            }, ARRAY_FILTER_USE_KEY);
            $ingredients = array_values($ingredients);
            $this->IngredientMeetRequirement( $target_crude_protein, $ingredient1, $ingredient2, $ingredient3,$ingredient4, $ingredient5 );
            $groupIngredients = $this->groupIngredient( $target_crude_protein, $ingredients );
            $this->getNewCrudeProteinValue( $groupIngredients );
            $this->addIngredientInGrp( $groupIngredients );
            $group = $this->groupIngregientIntoTwo( $groupIngredients );
            [
                'lower_ing' => $lower_ing, 
                'higher_ing' => $higher_ing, 
                'ingredients' => $ingredients, 
                'lower_percentage' => $lower_percentage, 
                'higher_percentage' => $higher_percentage, 
                'quantity' => $quantity
            ] = $this->formulateIngredient( $quantity, $target_crude_protein, $group[0], $group[1] );
            $this->unbundlegrpIngredient( $lower_percentage, $higher_percentage, $groupIngredients );
            $this->scaledownMultipleIng( $quantity, $groupIngredients );
            $this->checkIfQuantityMeetRequirement( $groupIngredients );
            $this->shldWeRepeatallProcess( $groupIngredients );
            return [
                'lower_ing' => $lower_ing, 
                'higher_ing' => $higher_ing, 
                'ingredients' => $ingredients, 
                'lower_percentage' => $lower_percentage, 
                'higher_percentage' => $higher_percentage, 
                'quantity' => $quantity,
                'group' => $groupIngredients
            ];
    }

    public function groupIngregientIntoTwo( $groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        $group[] = [
            "name" =>  "Lower",
            "crude_protein" => count( $lower_grp ) > 1 ? $groupIngredients[ 'sum_lower_grp_crude_protein' ] : $lower_grp[0]['old_crude_protein'],
            "quantity" => 0
        ];
        $group[] = [
            "name" =>  "Higher",
            "crude_protein" => count( $higher_grp ) > 1 ? $groupIngredients['sum_higher_grp_crude_protein' ] : $higher_grp[0]['old_crude_protein'],
            "quantity" => 0
        ];

        return $group;
    }

    public function unbundlegrpIngredient( $lower_percentage, $higher_percentage, &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        foreach( $lower_grp as $key => $grp ) {
            $groupIngredients['lower'][$key]['ini_percentage'] = $grp['percentage'];
            $groupIngredients['lower'][$key]['percentage'] = ( $lower_percentage * $grp['percentage'] ) / 100;
        }
        foreach( $higher_grp as $key => $grp ) {
            $groupIngredients['higher'][$key]['ini_percentage'] = $grp['percentage'];
            $groupIngredients['higher'][$key]['percentage'] = ( $higher_percentage * $grp['percentage'] ) / 100;
        }

        return $groupIngredients;
    }

    public function scaledownMultipleIng( $quantity, &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        foreach( $lower_grp as $key => $grp ) 
            $groupIngredients['lower'][$key]['quantity_required'] = number_format( ( $grp['percentage'] * $quantity ) / 100, 2);
        foreach( $higher_grp as $key => $grp ) 
            $groupIngredients['higher'][$key]['quantity_required'] = number_format( ( $grp['percentage'] * $quantity ) / 100, 2);
    }

    public function checkIfQuantityMeetRequirement( &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        $groupIngredients['lower'] = $this->check( $lower_grp );
        $groupIngredients['higher'] = $this->check( $higher_grp );
    }

    public function shldWeRepeatallProcess( &$groupIngredients ) {
        $lower_grp = $groupIngredients['lower'];
        $higher_grp = $groupIngredients['higher'];
        $lower_req = array_column( $lower_grp, 'meet_requirement');
        $index_lower = array_search( false, $lower_req );
        $this->lower_exceed = $this->WillPercentageBeGreaterthanHundred($lower_grp);
        if( count($lower_grp) > 1 && $index_lower > -1 ) {
            $groupIngredients['repeat_lower_grp'] = true;
            $this->repeat_lower = true;
            $this->lower = $this->lower ?? null;
            
        }
        else {
            $groupIngredients['repeat_lower_grp'] = false;
            $this->lower = $groupIngredients['lower'];
        }
        $higher_req = array_column( $higher_grp, 'meet_requirement');
        $index_higher = array_search( false, $higher_req );
        $this->higher_exceed = $this->WillPercentageBeGreaterthanHundred( $higher_grp );
        if( count($higher_grp) > 1 && $index_higher > -1 ) {
            $groupIngredients['repeat_higher_grp'] = true;
            $this->repeat_higher = true;
            $this->higher =$this->higher ?? null;
        } 
        else {
            $groupIngredients['repeat_higher_grp'] = false;
            $this->higher = $groupIngredients['higher'];
        }
    }

    public function WillPercentageBeGreaterthanHundred($group) {
        $quantities = array_column( $group, 'quantity');
        $index = array_search( max( $quantities ), $quantities );
        $percentage = $group[$index]['percentage'] + $this->step;
        if( $percentage > 100 )
            return true;
        return false;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
