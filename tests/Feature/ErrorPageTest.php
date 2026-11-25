<?php

it('renders the 404 page in the language chosen by the visitor', function () {
    $this->withCookie('locale', 'en')
        ->get('/missing-page')
        ->assertNotFound()
        ->assertSee("The page you're looking for doesn't exist.")
        ->assertSee('Return to homepage');
});
