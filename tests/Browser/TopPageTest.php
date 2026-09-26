<?php

it('top page: You are accessing from', function () {
    $page = visit(PEST_APP_URL . '/');

    $page->assertSee('You are accessing from');
});
