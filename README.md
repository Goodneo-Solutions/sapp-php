<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Goodneo-Solutions/sapp-releases/main/assets/sapp-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/Goodneo-Solutions/sapp-releases/main/assets/sapp-logo.svg" alt="sApp" height="56">
</picture>

# sApp

Build apps on Superroute: customer and driver apps that run for one or more service providers, and apps that use
Open Platform capabilities such as routing, geocoding and ETA. MIT licence.

Documentation: [open.superroute.ca/developer/sapp](https://open.superroute.ca/developer/sapp). Swift packages, the
Maven repository, the Go module and the example apps: [Goodneo-Solutions/sapp-releases](https://github.com/Goodneo-Solutions/sapp-releases).
PHP: [Goodneo-Solutions/sapp-php](https://github.com/Goodneo-Solutions/sapp-php).

| Package | Platform | Status |
|---|---|---|
| [`@superrouteca/sapp`](https://www.npmjs.com/package/@superrouteca/sapp) | JavaScript and TypeScript: web, Node 20+, React Native | 0.2.1 |
| [`@superrouteca/sapp-react`](https://www.npmjs.com/package/@superrouteca/sapp-react) | React: provider and hooks | 0.2.1 |
| [`@superrouteca/sapp-react-native`](https://www.npmjs.com/package/@superrouteca/sapp-react-native) | React Native: hooks, secure token store | 0.2.1 |
| [`sapp`](https://pub.dev/packages/sapp) | Dart and Flutter | 0.2.1 |
| [`SApp`](https://github.com/Goodneo-Solutions/sapp-releases) (Swift Package Manager) | iOS 15+, macOS 12+ | 0.2.1 |
| [`ca.superroute:sapp` / `sapp-android`](https://github.com/Goodneo-Solutions/sapp-releases) (Maven) | Kotlin on the JVM and Android 7+ | 0.2.1 |
| [`Superroute.SApp`](https://www.nuget.org/packages/Superroute.SApp) (NuGet) | .NET 8: Windows apps and servers | 0.2.1 |
| [`superroute-sapp`](https://pypi.org/project/superroute-sapp/) (PyPI, `import sapp`) | Python 3.9+: servers and scripts | 0.2.1 |
| [`superroute/sapp`](https://packagist.org/packages/superroute/sapp) (Packagist) | PHP 8.1+: servers | 0.2.1 |
| [`github.com/Goodneo-Solutions/sapp-releases/go`](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/go) | Go 1.21+: servers | 0.2.1 |
| [`ca.superroute:sapp-java`](https://github.com/Goodneo-Solutions/sapp-releases) (Maven) | Java 17+: servers, no dependencies | 0.2.1 |
| [`sapp_ui`](https://pub.dev/packages/sapp_ui) | Flutter UI kit: sign-in, notifications, wallet, orders, pickups | 0.2.1 |
| [`@superrouteca/sapp-ui`](https://www.npmjs.com/package/@superrouteca/sapp-ui) | Web UI kit: the same components as custom elements | 0.2.1 |
| `@superrouteca/sapp-react/ui` | React components over the web UI kit | 0.2.1 |
| [`SAppUI`](https://github.com/Goodneo-Solutions/sapp-releases) (Swift Package Manager) | SwiftUI UI kit: iOS 15+, macOS 12+ | 0.2.1 |
| [`ca.superroute:sapp-ui-android`](https://github.com/Goodneo-Solutions/sapp-releases) (Maven) | Jetpack Compose UI kit: Android 7+ | 0.2.1 |
| `@superrouteca/sapp-react-native/ui` | React Native UI kit | 0.2.1 |
| [`Superroute.SApp.Wpf`](https://www.nuget.org/packages/Superroute.SApp.Wpf) / [`Superroute.SApp.WinForms`](https://www.nuget.org/packages/Superroute.SApp.WinForms) (NuGet) | WPF and WinForms UI kits over `Superroute.SApp.UI` | 0.2.1 |

Every package of a release carries the same version. Version numbers never contain the digits 3 or 4.

## Profiles

- **Partner** — an app registered on the Open Platform console (`open.superroute.ca/open-api/apps`). It acts for one
  person at one service provider, with that person's sign-in and the capabilities the app was approved for and the
  provider switched on.
- **Open** — Open Platform capabilities (`open.superroute.ca/v1`). Inside an app, pass `getToken`, answered by your
  own backend with a short-lived token; an Open Platform API key is a server secret and never belongs in an app.

What a caller may do is decided by the server, never by the package installed.

## JavaScript

```js
import { createPartnerClient } from '@superrouteca/sapp';

const sr = createPartnerClient({
  appCode: 'p_acme_parcels',
  clientCode: 'k3m9q2',          // the service provider's public client code
  appKey: 'sp_appkey_…',         // restricted key for calls before sign-in
  tokenStore,                    // your secure storage: { get, set, clear }
  locale: 'en',
});

// Sign in with Google or Apple in the app…
await sr.auth.signInWithIdToken(googleIdToken);
// …or on the Superroute sign-in page:
const { url, verifier } = await sr.auth.authorizationUrl({ redirectUri: 'com.acme.parcels:/oauth' });
// open `url` in the system browser, then
await sr.auth.exchangeCode({ code, redirectUri: 'com.acme.parcels:/oauth', verifier });

const notifications = await sr.api.platformAppNotifications();
const me = await sr.graphql('{ partnerAppMe { app_code user_name } }');
```

## React and React Native

```jsx
import { SuperrouteProvider, useApi, useSession, createSecureTokenStore } from '@superrouteca/sapp-react-native';

const sr = createPartnerClient({ appCode: 'p_acme_parcels', clientCode, appKey, tokenStore: createSecureTokenStore(secureStorage) });

function Inbox() {
  const { signedIn } = useSession();
  const { data, loading, error, reload } = useApi((api) => api.platformAppNotifications(), [], { enabled: signedIn === true });
  …
}

<SuperrouteProvider client={sr}><Inbox /></SuperrouteProvider>
```

Sign-in on the Superroute page from a phone: `sr.auth.signInWithBrowser({ redirectUri, open })`, where `open(url,
redirectUri)` shows the page in an in-app browser session and answers the URL it returned to (null when closed).
`sr.auth.subscribe(fn)` is told when a session starts and ends.

## Dart and Flutter

```dart
import 'package:sapp/sapp.dart';

final sr = PartnerClient(appCode: 'p_acme_parcels', clientCode: 'k3m9q2', appKey: 'sp_appkey_…', tokenStore: store);
await sr.signInWithIdToken(googleIdToken);
final notifications = await sr.api.platformAppNotifications();
```

## Swift

```swift
// Package.swift
.package(url: "https://github.com/Goodneo-Solutions/sapp-releases", from: "0.2.1")
// in the target: .product(name: "SApp", package: "sapp-releases"), and .product(name: "SAppUI", package: "sapp-releases") for the UI kit
```

In Xcode: File > Add Package Dependencies…, `https://github.com/Goodneo-Solutions/sapp-releases`.

```swift
import SApp

let sr = PartnerClient(appCode: "p_acme_parcels", clientCode: "k3m9q2", appKey: "sp_appkey_…", tokenStore: KeychainTokenStore())
try await sr.signIn(idToken: googleIdToken)
let notifications = try await sr.api.platformAppNotifications()

// The Superroute sign-in page in an ASWebAuthenticationSession:
let request = try await sr.authorizationRequest(redirectURI: "com.acme.parcels:/oauth")
// … present request.url, then with the URL the session returned:
try await sr.completeSignIn(request, returnedURL: callbackURL)
```

Answers are `JSON` values (`answer["data"]?["items"]?.array`). `onSessionChange` is told when a session starts and ends.

## Kotlin and Android

```kotlin
// settings.gradle.kts
dependencyResolutionManagement {
    repositories {
        google()
        mavenCentral()
        maven { url = uri("https://goodneo-solutions.github.io/sapp-releases/maven") }
    }
}

// build.gradle.kts: ca.superroute:sapp on the JVM, sapp-android on Android, sapp-ui-android for the Compose kit
implementation("ca.superroute:sapp-android:0.2.1")
implementation("ca.superroute:sapp-ui-android:0.2.1")
```

```kotlin

val sr = PartnerClient(appCode = "p_acme_parcels", clientCode = "k3m9q2", appKey = "sp_appkey_…", tokenStore = KeystoreTokenStore(context))
sr.signInWithIdToken(googleIdToken)
val notifications = sr.api.platformAppNotifications()   // a kotlinx.serialization JsonElement
```

The sign-in page: open `sr.authorizationRequest(redirectUri).url` in a Custom Tab, then `sr.completeSignIn(request,
returnedUrl)`. `KeystoreTokenStore` encrypts the tokens with a key held in the Android Keystore.

## C# and .NET

```csharp
using Superroute.SApp;

using var sr = new PartnerClient("p_acme_parcels", clientCode: "k3m9q2", appKey: "sp_appkey_…", tokenStore: new ProtectedDataTokenStore());
await sr.SignInWithIdTokenAsync(googleIdToken);
var notifications = await sr.Api.PlatformAppNotificationsAsync();   // a System.Text.Json JsonNode
```

`ProtectedDataTokenStore` keeps the tokens encrypted for the current Windows user. `OnSessionChange` is told when a
session starts and ends.

## Python

```python
from sapp import PartnerClient, OpenClient

sr = PartnerClient('p_acme_parcels', client_code='k3m9q2', app_key='sp_appkey_…')
sr.sign_in_with_id_token(google_id_token)
notifications = sr.api.platform_app_notifications()

open = OpenClient(api_key=os.environ['SUPERROUTE_OPEN_KEY'])   # on a server
place = open.api.geo_forward(body={'address': '100 King St W, Toronto'})
```

Methods and arguments are snake_case (`platform_app_mark_notification_read(notification_id=…)`); answers are the
parsed JSON. The client is safe to share between threads: concurrent calls share one refresh.

## PHP

```php
use Superroute\SApp\PartnerClient;
use Superroute\SApp\OpenClient;

$sr = new PartnerClient('p_acme_parcels', clientCode: 'k3m9q2', appKey: 'sp_appkey_…');
$sr->signInWithIdToken($googleIdToken);
$notifications = $sr->api->platformAppNotifications();

$open = new OpenClient(apiKey: getenv('SUPERROUTE_OPEN_KEY'));
$place = $open->api->geoForward(['address' => '100 King St W, Toronto']);
```

`composer require superroute/sapp`. Optional parameters are named arguments; answers are decoded JSON arrays. Requests
go through curl when it is loaded, else PHP streams; pass your own `Transport` to use another HTTP client.

## Go

```go
import sapp "github.com/Goodneo-Solutions/sapp-releases/go"

sr, err := sapp.NewPartnerClient(sapp.PartnerOptions{AppCode: "p_acme_parcels", ClientCode: "k3m9q2", AppKey: "sp_appkey_…"})
_, err = sr.SignInWithIDToken(ctx, googleIDToken, nil)
notifications, err := sr.API.PlatformAppNotifications(ctx, sapp.PlatformAppNotificationsParams{})

open, err := sapp.NewOpenClient(sapp.OpenOptions{APIKey: os.Getenv("SUPERROUTE_OPEN_KEY")})
place, err := open.API.GeoForward(ctx, sapp.GeoForwardParams{Body: map[string]any{"address": "100 King St W, Toronto"}})
```

Each operation takes its parameters in a struct; optional ones are pointers (`sapp.String("ios")`, `sapp.Bool(true)`).
Answers decode into `any` (numbers as `float64`). Failures are `*sapp.Error`.

## Java

```java
// build.gradle.kts: repositories { maven("https://goodneo-solutions.github.io/sapp-releases/maven") }
implementation("ca.superroute:sapp-java:0.2.1")

PartnerClient sr = PartnerClient.builder("p_acme_parcels").clientCode("k3m9q2").appKey("sp_appkey_…").build();
sr.signInWithIdToken(googleIdToken);
Object notifications = sr.api.platformAppNotifications();

OpenClient open = OpenClient.builder().apiKey(System.getenv("SUPERROUTE_OPEN_KEY")).build();
Object place = open.api.geoForward(Map.of("address", "100 King St W, Toronto"));
```

Required parameters are arguments; optional ones, an optional body and files go in `Args`
(`api.x(Args.of().with("page", 2))`). The artifact has no dependencies: answers are plain `Map`, `List`, `String`,
`Long`, `Double`, `Boolean` or null (the built-in `Json` reads and writes them), so it never conflicts with the
Jackson or Gson version a server already carries.

## Webhooks

Every language verifies Superroute webhooks: the signature over the raw body, a timestamp inside the tolerance (300
seconds) so an old request cannot be replayed, and the event parsed from the native body or a CloudEvents envelope.

```js
import { verifyWebhook } from '@superrouteca/sapp';   // also from sapp-react and sapp-react-native

const event = await verifyWebhook(rawBody, req.headers, process.env.SUPERROUTE_WEBHOOK_SECRET);
if (await alreadyHandled(event.id)) return;            // an event can arrive more than once
```

| Language | Call |
|---|---|
| Dart | `verifyWebhook(body, headers, [secret])` |
| Swift | `try Webhooks.verify(body:headers:secrets:)` |
| Kotlin | `Webhooks.verify(body, headers, listOf(secret))` |
| C# | `Webhooks.Verify(body, headers, new[] { secret })` |
| Python | `verify_webhook(body, headers, secret)` |
| PHP | `Webhooks::verify($body, $headers, $secret)` |
| Go | `sapp.VerifyWebhook(body, r.Header, []string{secret}, sapp.WebhookOptions{})` |
| Java | `Webhooks.verify(body, headers, secret, WebhookOptions.defaults())` |

Pass the body exactly as received, never a re-encoded object. The secret is the endpoint's signing secret, as
`whsec_…` or plain; during a key rotation pass both. The scheme is the way the endpoint signs:

| Scheme | Sender | Headers |
|---|---|---|
| `standard` (default) | any endpoint with Standard Webhooks switched on | `webhook-id`, `webhook-timestamp`, `webhook-signature` |
| `tenant` | order, tracking, driver and device-order webhooks | `X-Webhook-Signature-V2`, `X-Webhook-Timestamp` |
| `partner_locker`, `tpd` | partner locker deliveries, third-party delivery providers | `X-Webhook-Signature: v1=…`, `X-Webhook-Delivery-Id`, `X-Webhook-Timestamp` |
| `loading_plan` | loading plan callbacks | `X-Webhook-Signature: v1=…`, `X-Webhook-Timestamp` |
| `open_platform` | Open Platform job callbacks | `X-Open-Signature`, `X-Open-Timestamp` |
| `dataset` | dataset webhooks | `X-Webhook-Signature` (the timestamp is not signed) |

Tenant webhooks keep the time of the first try on their retries, so their tolerance is 12,600 seconds. The event
carries `id`, `type` (without the CloudEvents `ca.superroute.` prefix), `time`, `subject`, `source`, `business`,
`attempt`, `format` (`native`, `ce_binary`, `ce_structured`) and `data`. A failure is a
`WebhookVerificationError` / `WebhookVerificationException` with a `code`: `missing_header`, `invalid_timestamp`,
`timestamp_out_of_tolerance`, `invalid_signature`, `invalid_secret`, `invalid_payload` or `unknown_scheme`. The test
vectors are in `testdata/webhooks/vectors.json` (`tools/webhook_vectors.py`).

## Flutter UI kit

```dart
SuperrouteScope(
  client: sr,
  theme: ThemeTokens.fromBranding(bootstrap['client']['settings']['branding']),
  onSignInRequired: () => router.go('/sign-in'),
  child: SrPickupList(onShowCode: (value) => showQr(value)),
)
```

Components, for customer apps: `SrSignIn`, `SrNotificationInbox`, `SrWalletSummary`, `SrOrderList`, `SrPickupList`,
`SrOrderDetail`, `SrTracking`, `SrNotificationPreferences`, `SrAccountPrivacy`, `SrLockerMap` (with
`SrSMapAdapter(controller)` for the app's sMap map), `SrReturnList`, `SrReturnForm`, `SrShippingOrderForm`, the store
and mall (`SrStoreDirectory`, `SrStorePage`, `SrProductDetail`, `SrCart`, `SrCheckout`) and group buy
(`SrGroupBuyList`, `SrGroupBuy`, `SrGroupBuyOrders`, `SrGroupBuyOrder`, and for group leaders `SrGroupBuyLead`,
`SrGroupBuyLeading`, `SrGroupBuyLeadingGroup`); for driver apps: `SrDriverRoutes`,
`SrRouteStops`, `SrStop` (photos, a signature pad, the delivery PIN), `SrDriverDuty`, `SrDriverPool`, `SrRouteBoards`,
`SrRouteBoard`, `SrBoardClaims`, `SrDriverEarnings`, `SrScanLookup`. Files, the position, the scanner and card payments
are the app's: a component takes a callback for them (`pickFile`, `pickPhoto`, `getPosition`, `scan`) or hands the
payment or a link to share to the app (`onPayment`, `onShare`). They raise events and
never navigate; what each shows, its states and strings are in the [UI kit reference](https://open.superroute.ca/developer/sapp), the same on every
platform. Strings in 14 languages live in `ui/strings/` (`tools/ui_strings.py`).

## Web and React UI kit

```html
<script type="module">
  import { configure } from '@superrouteca/sapp-ui';
  configure({ client: sr, theme: themeFromBranding(branding), locale: 'en' });
</script>
<sr-pickup-list></sr-pickup-list>
```

Elements: `sr-sign-in`, `sr-notification-inbox`, `sr-wallet-summary`, `sr-order-list`, `sr-pickup-list`,
`sr-order-detail`, `sr-tracking`, `sr-notification-preferences`, `sr-account-privacy`, `sr-locker-map` (`map =
smapAdapter(sMapMap)`), `sr-return-list`, `sr-return-form`, `sr-shipping-order-form`, `sr-store-directory`,
`sr-store-page`, `sr-product-detail`, `sr-cart`, `sr-checkout`, `sr-group-buy-list`, `sr-group-buy`,
`sr-group-buy-orders`, `sr-group-buy-order`, for group leaders `sr-group-buy-lead`, `sr-group-buy-leading`,
`sr-group-buy-leading-group`, and for driver apps `sr-driver-routes`, `sr-route-stops`, `sr-stop`,
`sr-driver-duty`, `sr-driver-pool`, `sr-route-boards`, `sr-route-board`, `sr-board-claims`, `sr-driver-earnings`,
`sr-scan-lookup`. Each raises `sr-*` events (`sr-open`, `sr-created`, `sr-payment`, `sr-completed`, …, listed in the
types) and `sr-sign-in-required`. The kit never draws a map: the locker map uses the app's own sMap map through the adapter. In React:

```jsx
import { PickupList } from '@superrouteca/sapp-react/ui';
<SuperrouteProvider client={sr}><PickupList onShowCode={showQr} /></SuperrouteProvider>
```

## Native UI kits

The same components on SwiftUI, Jetpack Compose, React Native, WPF and WinForms, following the [UI kit reference](https://open.superroute.ca/developer/sapp)
exactly as the Flutter and web kits do, with the same strings in 14 languages (generated from `ui/strings/` by
`tools/ui_strings.py`). Each component keeps its state and calls in a testable model (an `ObservableObject` on SwiftUI,
a state holder on Compose, a view model in `Superroute.SApp.UI` shared by WPF and WinForms) and draws it with the
platform's own controls. The app supplies what the kit leaves to it: images on Compose, the in-app browser for sign-in,
files, photos, the position, the scanner, card payments and the sMap map through its adapter.

```swift
import SAppUI
SuperrouteScope(client: client, theme: ThemeTokens(branding: branding)) {
    PickupList(onShowCode: { code in showQR(code) })
}
```

```kotlin
SuperrouteScope(client = client, theme = ThemeTokens(branding)) {
    PickupList(onShowCode = { code -> showQr(code) })
}
```

```jsx
import { PickupList } from '@superrouteca/sapp-react-native/ui';
<SuperrouteProvider client={sr}><PickupList onShowCode={(code) => showQr(code)} /></SuperrouteProvider>
```

Example apps, built against the published packages: [SwiftUI](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/examples/swiftui), [Android](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/examples/android), [React Native](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/examples/react-native), [WPF](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/examples/wpf) and [WinForms](https://github.com/Goodneo-Solutions/sapp-releases/tree/main/examples/winforms).

## Errors

Every failed call throws one error type (`SuperrouteError` / `SuperrouteException`) with the HTTP `status`, the
server's `code` (`invalid_token`, `insufficient_scope`, `access_denied`, `APP_ACCESS_DENIED`, …) and the parsed body.
`signInRequired` is true when the person must sign in again. Repeatable calls are retried on a lost connection and
on temporary server errors; writes are never retried.

## How the clients are made

The API methods are generated from the specs Superroute publishes (`specs/`): one method per operation. Never edit
`*/generated/*`; run `tools/sync-specs.sh /path/to/Superroute` to copy newer specs and regenerate. CI fails when the
generated files do not match the specs.

## Tests

```sh
npm install && npm test        # js, react, react-native
cd dart && dart pub get && dart test
swift test
cd kotlin && ./gradlew :sapp:test
dotnet test dotnet/tests/Superroute.SApp.Tests/Superroute.SApp.Tests.csproj
pip install -e python && pytest python/tests
cd php && composer install && vendor/bin/phpunit
cd go && go test ./...
cd java && ./gradlew test
cd flutter_ui && flutter test
```
