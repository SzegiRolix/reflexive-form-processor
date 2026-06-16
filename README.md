Reflexive Communications
PHP próbamunka

Kapcsolatfelvételi űrlap beküldéseinek feldolgozása egy tiszta, jól tesztelhető
PHP service class segítségével. A megoldás validálja a beérkező adatokat, kezeli
a kontaktokat (e-mail alapján egyedi), és a tényleges adatbázis-műveleteket
repository interfészeken keresztül delegálja.

A repository-k konkrét (adatbázis-alapú) implementációja, a kiírásnak
megfelelően **nem** része a megoldásnak; a teszteléshez in-memory
implementációk készültek (`tests/Support/`).

## Követelmények

- PHP **8.2+** (fejlesztve és tesztelve PHP 8.3-on; 8.2-kompatibilis szintaxis)
- Composer
- `ext-mbstring`, `ext-filter` (alap PHP build része)

## Telepítés

```bash
composer install
```

## Tesztek futtatása

```bash
# Teljes csomag (unit + integráció)
composer test
# vagy közvetlenül:
vendor/bin/phpunit

# Csak unit tesztek
composer test:unit

# Csak integrációs tesztek
composer test:integration
```

Várt eredmény: **OK (28 tests, 66 assertions)**.

## Architektúra

A `process()` három, jól elkülönülő lépésre bomlik:

1. **Validálás + normalizálás** → `ContactFormData::fromArray()`
   A nyers tömbből egy immutábilis, már validált value object jön létre. Ha
   bármelyik mező hibás, `ValidationException` keletkezik, ami **az összes** hibát
   mezőnként visszaadja (`getErrors()`), nem csak az elsőt.
2. **Kontakt feloldása** → `getContactByEmail()` alapján vagy `create()`,
   vagy `update()`. A kontaktot az e-mail cím azonosítja egyedileg.
3. **Beküldés mentése** → a submission a feloldott `contact_id`-hez kötve
   tárolódik. Egy kontakthoz tetszőleges számú beküldés tartozhat.

### Főbb tervezési döntések

- **Dependency Injection:** mindkét repository konstruktorban érkezik
  (`ContactRepositoryInterface`, `ContactFormSubmissionRepositoryInterface`).
- **Időkezelés tesztelhetően:** a `ClockInterface` (alapból `SystemClock`)
  injektálható; ettől az „időbélyeg hiányában a feldolgozás ideje" szabály
  determinisztikusan tesztelhető (`FixedClock`). A `ClockInterface` alakja
  szándékosan azonos a PSR-20-szal.
- **Immutábilis value object:** a `ContactFormData` `readonly` mezőkkel,
  privát konstruktorral és validáló named constructorral – ha létezik egy
  példány, az garantáltan érvényes adatot hordoz.
- **Hibajelzés kivétellel:** mivel a `process()` visszatérési típusa `void`,
  a hibajelzés `ValidationException`-nel történik. Hiba esetén semmi nem
  kerül mentésre (a validálás minden repository-hívás előtt lefut).
- **Statikus analízisre kész:** a megadott interfészek `array{...}` shape
  docblockjai megmaradtak; a kód PHPStan/Psalm-barát.

## Validációs szabályok

| Mező | Szabály |
|------|---------|
| `first_name` | kötelező, string, nem üres (trim után), max. 255 karakter |
| `last_name` | kötelező, string, nem üres, max. 255 karakter |
| `email` | kötelező, érvényes e-mail (`FILTER_VALIDATE_EMAIL`), max. 255 karakter; trimmelve és kisbetűsítve tárolódik |
| `field` | kötelező, string, nem üres, max. 255 karakter |
| `service` | kötelező, string, nem üres, max. 255 karakter |
| `message` | kötelező, string, nem üres, max. 65 535 karakter |
| `timestamp` | opcionális; ha hiányzik/üres → a feldolgozás ideje; ha meg van adva, érvényes dátum/idő stringnek kell lennie, `Y-m-d H:i:s` formátumra normalizálva tárolódik |

## Feltételezések

A kiírás üzleti leírása néhány ponton értelmezést igényelt; az alábbi
döntéseket hoztam (mind könnyen módosítható egy helyen, a `ContactFormData`-ban):

- **`field`, `service`, `message` kötelező.** Egy kapcsolatfelvételi
  űrlapnál ezek érdemi tartalmat hordoznak, ezért kötelezőként kezelem.
  Ha a valós űrlapon opcionálisak, a `validateRequiredString` hívások
  cseréje elég.
- **`field` / `service` szabad szöveg.** Nem ismert a megengedett értékek
  listája, ezért nem-üres stringként validálom. Ha van fix lista (enum),
  az `in_array` ellenőrzés egy helyen beilleszthető.
- **E-mail normalizálás:** az e-mail trimmelve és kisbetűsítve azonosít, így
  a `John@Example.com` és a `john@example.com` ugyanaz a kontakt.
- **Meglévő kontakt frissítése:** a `first_name` és `last_name` frissül a
  legújabb beküldés szerint (az e-mail az identitáskulcs, nem írjuk felül).
- **Ismeretlen extra mezők** a bemenetben figyelmen kívül maradnak (nem
  okoznak hibát).

## Projektstruktúra

```
src/
  Clock/
    ClockInterface.php          # Idő-absztrakció (PSR-20 alakú)
    SystemClock.php             # Alapértelmezett, rendszeridő-alapú clock
  Contract/
    FormProcessorInterface.php                    # Implementálandó interfész
    ContactRepositoryInterface.php                # Megadott interfész
    ContactFormSubmissionRepositoryInterface.php  # Megadott interfész
  Exception/
    ValidationException.php     # Mezőnként gyűjtött validációs hibák
  ContactFormData.php           # Immutábilis, validált value object
  FormProcessor.php             # A service (FormProcessorInterface implementáció)
tests/
  Support/
    FixedClock.php                                # Determinisztikus teszt-clock
    InMemoryContactRepository.php                 # Fake repository
    InMemoryContactFormSubmissionRepository.php   # Fake repository
  Unit/
    ContactFormDataTest.php     # Validálás / normalizálás
    FormProcessorTest.php       # Service logika (PHPUnit mockokkal)
  Integration/
    FormProcessorIntegrationTest.php   # Végponttól végpontig, in-memory repókkal
composer.json
phpunit.xml
```

## Példa használat

```php
use Reflexive\ContactForm\FormProcessor;

// $contactRepository és $submissionRepository: a saját (DB-alapú) implementációk
$processor = new FormProcessor($contactRepository, $submissionRepository);

try {
    $processor->process([
        'first_name' => 'Anna',
        'last_name'  => 'Kovács',
        'email'      => 'anna.kovacs@example.com',
        'field'      => 'Médiakapcsolatok',
        'service'    => 'Sajtófigyelés',
        'message'    => 'Szeretnék ajánlatot kérni.',
        // 'timestamp' opcionális
    ]);
} catch (\Reflexive\ContactForm\Exception\ValidationException $e) {
    // $e->getErrors() => ['email' => ['The "email" field must be ...'], ...]
}
```
