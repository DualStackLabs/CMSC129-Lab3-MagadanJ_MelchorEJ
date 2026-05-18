<?php

test('the homepage redirects to the journal dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect('/entries');
});
