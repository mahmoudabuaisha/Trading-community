<?php

/*
| Commitment rewards. A member earns points for every day on which they
| finished all of that day's tasks; points unlock avatar frames and titles.
| Keys are machine values — their names live in the front end's translations.
*/

return [
    'points_per_perfect_day' => 10,

    // key => points needed
    'frames' => ['none' => 0, 'bronze' => 30, 'silver' => 100, 'gold' => 250, 'royal' => 500],
    'titles' => ['committed' => 50, 'achiever' => 150, 'leader' => 400, 'legend' => 800],
];
