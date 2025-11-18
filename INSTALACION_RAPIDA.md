# Guía Rápida de Instalación

Esta guía te ayudará a integrar el paquete Laravel Mirakl en tu middleware existente de Laravel 11.9 con el patrón Porto.

## 1. Instalación del Paquete

### Opción A: Usando Composer (Recomendado cuando publiques en Packagist)

```bash
composer require homedoctor-es/laravel-mirakl
```

### Opción B: Instalación Local

Si aún no has publicado el paquete en Packagist:

1. Copia la carpeta `laravel-mirakl` a un directorio de paquetes locales (ej: `packages/`)

2. Añade al `composer.json` de tu proyecto principal:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-mirakl"
        }
    ],
    "require": {
        "homedoctor-es/laravel-mirakl": "*"
    }
}
```

3. Ejecuta:

```bash
composer update homedoctor-es/laravel-mirakl
```

## 2. Configuración

### Publicar Configuración

```bash
php artisan vendor:publish --provider='HomedoctorEs\Laravel\Mirakl\MiraklServiceProvider'
```

### Configurar Variables de Entorno

Añade a tu `.env`:

```env
MIRAKL_API_URL=https://your-instance.mirakl.net/api
MIRAKL_API_KEY=your_api_key_here
MIRAKL_SHOP_ID=your_shop_id_here
MIRAKL_TIMEOUT=30
```

## 3. Integración con tu Middleware Existente

### Estructura Recomendada en tu Proyecto

Siguiendo el patrón Porto que ya usas:

```
app/
└── Containers/
    └── Mirakl/
        ├── Actions/
        │   ├── SyncOffersAction.php
        │   └── SyncMiraklToOdooAction.php
        ├── Models/
        │   └── Offer.php
        ├── Tasks/
        │   ├── GetOffersTask.php
        │   ├── GetOrdersTask.php
        │   └── GetProductsTask.php
        └── UI/
            └── CLI/
                └── Commands/
                    └── SyncOffersCommand.php
```

### Copiar Ejemplos

Los ejemplos incluidos en `examples/Porto/` están listos para usar. Simplemente cópialos a tu proyecto:

```bash
# Desde la raíz de tu proyecto
cp -r vendor/homedoctor-es/laravel-mirakl/examples/Porto/* app/Containers/Mirakl/
```

O si instalaste localmente:

```bash
cp -r packages/laravel-mirakl/examples/Porto/* app/Containers/Mirakl/
```

## 4. Migración para el Modelo Offer

Si vas a usar el modelo Offer de ejemplo, crea la migración:

```bash
php artisan make:migration create_mirakl_offers_table
```

Contenido de la migración:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mirakl_offers', function (Blueprint $table) {
            $table->id();
            $table->string('mirakl_id')->unique();
            $table->string('sku')->index();
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(0);
            $table->string('state')->index();
            $table->boolean('active')->default(true)->index();
            $table->json('raw_data')->nullable();
            $table->timestamp('mirakl_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sku', 'active']);
            $table->index(['state', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mirakl_offers');
    }
};
```

Ejecuta la migración:

```bash
php artisan migrate
```

## 5. Registrar Comandos

En `app/Containers/Mirakl/UI/CLI/Commands/`, asegúrate de que tus comandos están registrados.

Laravel 11 los auto-descubre, pero puedes verificar con:

```bash
php artisan list | grep mirakl
```

## 6. Integración con Odoo

### Ajustar las Rutas de Importación

En `SyncMiraklToOdooAction.php`, actualiza las referencias a tus Tasks de Odoo:

```php
use App\Containers\Odoo\Tasks\CreateProductTask;
use App\Containers\Odoo\Tasks\UpdateProductStockTask;
```

### Ejemplo de Task de Odoo

Si aún no tienes estas Tasks, aquí un ejemplo básico:

```php
namespace App\Containers\Odoo\Tasks;

use App\Ship\Parents\Tasks\Task;
use App\Containers\Odoo\Models\Product; // Tu modelo Odoo

class CreateProductTask extends Task
{
    public function run(array $productData)
    {
        $product = new Product();
        return $product->create($productData);
    }
}
```

## 7. Uso Básico

### Desde un Controlador

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOffersRequest;

public function index()
{
    $request = new GetOffersRequest();
    $offers = Mirakl::getOffers($request);
    
    return response()->json($offers);
}
```

### Desde una Action

```php
use App\Containers\Mirakl\Actions\SyncOffersAction;

public function __construct(
    private SyncOffersAction $syncOffersAction
) {}

public function sync()
{
    $result = $this->syncOffersAction->run();
    
    return response()->json($result);
}
```

### Desde la Línea de Comandos

```bash
# Sincronizar todas las ofertas
php artisan mirakl:sync-offers

# Con filtros
php artisan mirakl:sync-offers --sku=ABC123
php artisan mirakl:sync-offers --state=1100
php artisan mirakl:sync-offers --since="2024-01-01"
```

## 8. Integración con EventBridge

Si ya usas `homedoctor-es/laravel-eventbridge-pubsub`, los ejemplos ya incluyen eventos:

- `mirakl.sync.offers.started`
- `mirakl.sync.offers.progress`
- `mirakl.sync.offers.completed`
- `mirakl.sync.offers.failed`

### Suscribirse a Eventos

En tu `EventServiceProvider` o listener:

```php
use HomedoctorEs\Laravel\EventBridge\Facades\EventBridge;

EventBridge::subscribe('mirakl.sync.offers.completed', function ($event) {
    Log::info('Mirakl sync completed', $event->data);
});
```

## 9. Programar Sincronizaciones

En `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sincronizar ofertas cada hora
    $schedule->command('mirakl:sync-offers')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
}
```

## 10. Testing

### Test Básico

```php
use Tests\TestCase;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;

class MiraklIntegrationTest extends TestCase
{
    public function test_can_fetch_offers()
    {
        $request = new GetOffersRequest();
        $offers = Mirakl::getOffers($request);
        
        $this->assertNotNull($offers);
    }
}
```

## Estructura Final del Proyecto

```
tu-middleware-laravel/
├── app/
│   └── Containers/
│       ├── Mirakl/          ← Nuevo contenedor
│       │   ├── Actions/
│       │   ├── Models/
│       │   ├── Tasks/
│       │   └── UI/
│       └── Odoo/            ← Tu contenedor existente
│           ├── Actions/
│           ├── Models/
│           └── Tasks/
├── config/
│   ├── mirakl.php           ← Nueva configuración
│   └── odoo.php             ← Tu configuración existente
└── vendor/
    └── homedoctor-es/
        └── laravel-mirakl/  ← Paquete instalado
```

## Siguientes Pasos

1. **Personaliza los ejemplos** según tus necesidades específicas
2. **Implementa la lógica de mapeo** entre Mirakl y Odoo
3. **Configura los schedulers** para sincronizaciones automáticas
4. **Añade tests** para tu integración
5. **Monitoriza los logs** de sincronización

## Recursos Adicionales

- `README.md` - Documentación completa del paquete
- `STRUCTURE.md` - Estructura detallada del paquete
- `ADVANCED_EXAMPLES.md` - Ejemplos avanzados de uso
- [Documentación oficial de Mirakl](https://developer.mirakl.com/)

## Soporte

Para cualquier duda o problema, revisa:
1. Los ejemplos en `examples/Porto/`
2. Los archivos de documentación (.md)
3. El código fuente del paquete

¡Listo! Ya tienes todo configurado para empezar a trabajar con Mirakl en tu middleware. 🚀
