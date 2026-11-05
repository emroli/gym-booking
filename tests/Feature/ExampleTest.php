<?php

it('opens on the schedule', function () {
    $this->get(route('home'))->assertRedirect(route('schedule'));
});
