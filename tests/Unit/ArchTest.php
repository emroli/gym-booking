<?php

use Illuminate\Database\Eloquent\Model;

arch('actions are final')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Actions\Fortify');

arch('actions are readonly')
    ->expect('App\Actions')
    ->classes()
    ->toBeReadonly()
    ->ignoring('App\Actions\Fortify');

arch('data objects are final and readonly')
    ->expect('App\Data')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('models are Eloquent models')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('policies are named consistently')
    ->expect('App\Policies')
    ->classes()
    ->toHaveSuffix('Policy');

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
