<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Taches planifiees
|--------------------------------------------------------------------------
|
| Necessite un ordonnanceur qui appelle `php artisan schedule:run` chaque
| minute (cron en production, ou le service dedie du docker-compose).
|
*/

// Les jetons expires restent en base apres leur date de peremption : ils ne
// donnent plus acces a rien, mais la table grossit indefiniment.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Rappels de paniers recurrents. Quotidien : la granularite du besoin est le
// jour, pas l'heure.
Schedule::command('axizan:send-basket-reminders')->dailyAt('08:00');
