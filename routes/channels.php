<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.users', function ($user) {
    return $user->hasAnyRole(['manager', 'ceo']);
});

Broadcast::channel('admin.permissions', function ($user) {
    return $user->hasAnyRole(['manager', 'ceo']);
});

Broadcast::channel('admin.tokens', function ($user) {
    return $user->hasAnyRole(['manager', 'ceo']);
});
