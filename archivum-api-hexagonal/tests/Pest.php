<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot Laravel through Tests\TestCase. Unit and Architecture
| tests run without the framework.
|
*/

pest()->extend(TestCase::class)->in('Feature');
