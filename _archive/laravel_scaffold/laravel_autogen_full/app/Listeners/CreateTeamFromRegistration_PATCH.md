### PATCH al Listener `CreateTeamFromRegistration`

Dentro del método `handle(...)`, después de crear el Team, agrega:

```php
use App\Jobs\GenerateTeamScaffold;

// ...
$reg->team_id = $team->id;
$reg->save();

// Crear JSON público + placeholders (banner/badge) y asegurar branding
GenerateTeamScaffold::dispatch($team->id);
```

Esto complementa al job `ProvisionTeamAssets` (si lo estás usando). Puedes usar solo `GenerateTeamScaffold` porque ya genera JSON y placeholders; `ProvisionTeamAssets` se enfoca en el logo.png.
