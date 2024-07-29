<?php

test('Exception Is Thrown', function () {
    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid workflow! No such class:');
    //   expect($this->get('/workflow/dud'))->toThrow(\Exception::class); // ->toMatch('/^Invalid workflow prefix\: Dud.*$/i');  ->toThrow('Invalid workflow prefix: Dud.');

    $response = $this->get('/workflow/dud');

});

it('throws an exception', function () {
    $this->withoutExceptionHandling();
    $this->get('/workflow/dud');
})->throws(\RuntimeException::class);
