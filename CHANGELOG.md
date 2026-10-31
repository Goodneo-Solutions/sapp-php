# 0.2.5

## SDK

### Every language
- **Quotes without signing in**: read the service provider's quote form and get a price for a line (local delivery, pickup, point to point, shipping or express) with the app key alone.
- **Pickup points without signing in**: list the service provider's smart lockers and pickup point counters near a position or by search, with what each offers.
- **Widget settings**: read the settings of an sWidget by its key, or load a widget the service provider enabled for your app by its id.
- **Route editing on the Open Platform**: add new points, such as a stop dropped on a map, to a distance snapshot so the next insert or move can use them.
- **More about a refused call**: the error now carries the answer's response headers and how many seconds to wait before trying again (`Retry-After`), beside the status and the request id it already had.

### .NET
- **Waiting after too many requests**: a `Retry-After` that is not a number of seconds now waits 1 second and tries again, as every other language does.

## UI kits

### Flutter, web, React, React Native, SwiftUI, Jetpack Compose, WPF and WinForms
- **Guest quote**: a price form built from the service provider's quote lines — origin, destination postcode and country, units and packages — showing the price before tax, the tax and the total, and how long the price is valid.
- **Guest shipping order**: a shipment without an account — sender, recipient and packages, a choice of prices, a code sent to the person's email or phone, then the payment page handed to the app; afterwards the shipment's status, tracking numbers, cancelling after a confirmation, the label handed to the app as a file, and the person's own shipments.
- **Errors reach the app**: every component reports each failed call to the app (`sr-error` on the web, `onError` on React, React Native, Flutter, SwiftUI and Jetpack Compose, and on `SuperrouteScope`, the `ErrorOccurred` event in .NET) with the code, message, status, request id and seconds to wait, as well as showing it.

### SwiftUI, Jetpack Compose, WPF and WinForms
- **Dark mode and corner radius**: theme tokens take a light, dark or automatic mode (following the device) and a corner radius, set by the app or read from the service provider's branding.

# 0.2.2

## Breaking changes

### Every package and every UI kit
- **Smart-locker operations moved to sLocker**: this is a breaking change for anyone calling smart-locker operations. The 29 locker pickup, locker location, locker storage and locker shop methods are no longer in the sApp packages, and the pickup list and the locker map are no longer in the Flutter, web, React, React Native, SwiftUI, Jetpack Compose, WPF and WinForms UI kits. sLocker 0.2.0 provides them under the same names, on top of the sApp core. Store orders stay in sApp, the locker open and refund of a store order included.

| sApp 0.2.1 method (operationId) | Request | From 0.2.2 |
|---|---|---|
| `platformAppCollectPickup` | `POST /app/v1/pickups/{deliveryId}/collect` | sLocker, same operation name |
| `platformAppHandOverPickup` | `POST /app/v1/pickups/{deliveryId}/handoff` | sLocker, same operation name |
| `platformAppLockerLocation` | `GET /app/v1/locker-locations/{locationId}` | sLocker, same operation name |
| `platformAppLockerLocationGuide` | `GET /app/v1/locker-locations/{locationId}/guide` | sLocker, same operation name |
| `platformAppLockerLocationGuideFeedback` | `POST /app/v1/locker-locations/{locationId}/guide/feedback` | sLocker, same operation name |
| `platformAppLockerLocations` | `GET /app/v1/locker-locations` | sLocker, same operation name |
| `platformAppLockerShopCatalog` | `GET /app/v1/locker-locations/{locationId}/shop` | sLocker, same operation name |
| `platformAppLockerShopCreateOrder` | `POST /app/v1/locker-shop/orders` | sLocker, same operation name |
| `platformAppLockerShopIssueAccessCode` | `POST /app/v1/locker-shop/access-codes` | sLocker, same operation name |
| `platformAppLockerShopItems` | `GET /app/v1/locker-shop/items` | sLocker, same operation name |
| `platformAppLockerShopOpen` | `POST /app/v1/locker-shop/orders/{id}/open` | sLocker, same operation name |
| `platformAppLockerShopOrder` | `GET /app/v1/locker-shop/orders/{id}` | sLocker, same operation name |
| `platformAppLockerShopOrders` | `GET /app/v1/locker-shop/orders` | sLocker, same operation name |
| `platformAppLockerShopRefundRequest` | `POST /app/v1/locker-shop/orders/{id}/refund-request` | sLocker, same operation name |
| `platformAppLockerStorageClaim` | `POST /app/v1/locker-storage/rentals/claim` | sLocker, same operation name |
| `platformAppLockerStorageDelivery` | `POST /app/v1/locker-storage/rentals/{id}/delivery` | sLocker, same operation name |
| `platformAppLockerStorageKeep` | `POST /app/v1/locker-storage/rentals/{id}/keep` | sLocker, same operation name |
| `platformAppLockerStorageOnwardQuote` | `POST /app/v1/locker-storage/rentals/{id}/onward-quote` | sLocker, same operation name |
| `platformAppLockerStoragePickupSite` | `POST /app/v1/locker-storage/rentals/{id}/pickup-site` | sLocker, same operation name |
| `platformAppLockerStoragePickupSiteCancel` | `POST /app/v1/locker-storage/rentals/{id}/pickup-site/cancel` | sLocker, same operation name |
| `platformAppLockerStorageRental` | `GET /app/v1/locker-storage/rentals/{id}` | sLocker, same operation name |
| `platformAppLockerStorageRentals` | `GET /app/v1/locker-storage/rentals` | sLocker, same operation name |
| `platformAppLockerStorageResendCode` | `POST /app/v1/locker-storage/rentals/{id}/resend-code` | sLocker, same operation name |
| `platformAppLockerStorageReturnTime` | `POST /app/v1/locker-storage/rentals/{id}/return-time` | sLocker, same operation name |
| `platformAppLockerStorageShipment` | `POST /app/v1/locker-storage/rentals/{id}/shipment` | sLocker, same operation name |
| `platformAppPickup` | `GET /app/v1/pickups/{deliveryId}` | sLocker, same operation name |
| `platformAppPickups` | `GET /app/v1/pickups` | sLocker, same operation name |
| `platformAppTakeBackPickup` | `POST /app/v1/pickups/{deliveryId}/handoff/revoke` | sLocker, same operation name |
| `platformAppUpdateLockerPreference` | `PUT /app/v1/locker-locations/{locationId}/preference` | sLocker, same operation name |
| UI component pickup list: Flutter `SrPickupList`, web `sr-pickup-list` (`SrPickupList`), React / React Native / SwiftUI / Compose `PickupList`, .NET `PickupListViewModel` and the WPF / WinForms `PickupList` control | — | sLocker UI kit |
| UI component locker map and its sMap adapter: Flutter `SrLockerMap` (`SrSMapAdapter`, `SrMapAdapter`, `SrMapPoint`), web `sr-locker-map` (`SrLockerMap`, `smapAdapter`), React / React Native `LockerMap` (`smapAdapter`), SwiftUI `LockerMap` (`SRSMapAdapter`, `SRMapAdapter`, `SRMapPoint`), Compose `LockerMap` (`SrSMapAdapter`, `SrMapAdapter`, `SrMapPoint`), .NET `LockerMapViewModel` (`SMapAdapter`, `IMapAdapter`, `MapPoint`) and the WPF / WinForms `LockerMap` control | — | sLocker UI kit |

## SDK

### Every language
- **Building on the sApp core**: the partner and open clients take a hand-made operation description (method, path, authentication, query names, header parameters, body kind) and call it exactly as a generated method: the same sign-in, refresh, idempotency keys, retries and errors. sLocker is built this way.
- **Header parameters**: operations that take an idempotency key or a guest order, contact or cart token now accept it as an optional argument and send it as its header; the client's own sign-in headers always win.
- **Idempotency keys made for you**: an order, top-up or label call that takes an idempotency key gets a fresh one when none is given, and keeps it when the call is sent again after a sign-in refresh.
- **Extra query parameters**: every method takes query parameters the published spec does not list (such as `multiple=1` on the address-by-postcode lookup), added after the listed ones and never replacing one.
- **Refusals with status 200 are errors**: an answer with `"result": false` is now raised as the SDK's error even when its status is 200, and the error carries the code, message, field errors, reasons and data of the answer. Answers without `result`, or with `"result": true`, are unchanged. Code that checked `result` on an answer should catch the error instead.

### Dart and Flutter
- **Secure token storage for Flutter**: `SecureTokenStore` in the Flutter UI kit keeps a partner app's sign-in in the Keychain or the Android Keystore, ready to pass as the client's token store.
- **Transport library**: `package:sapp/transport.dart` gives packages that add their own connection profile the same request building, idempotency keys, extra query, retries and errors as the SDK's clients. Nothing changes for code that uses `package:sapp/sapp.dart`.

## Licence

### Every package
- **Superroute SDK License**: from the next version the packages are licensed under the Superroute SDK License — see `LICENSE` and [open.superroute.ca/developer/license](https://open.superroute.ca/developer/license). Versions published before keep the licence they were published with.

# 0.2.1

## SDK

### Python, PHP, Go and Java
- **Four more languages**: `superroute-sapp` on PyPI, `superroute/sapp` on Packagist, the Go module `github.com/Goodneo-Solutions/sapp-releases/go` and `ca.superroute:sapp-java` from the sApp Maven repository, with the same sign-in, partner app API and Open Platform calls as the other languages and no extra dependencies.

### Webhooks
- **Verify Superroute webhooks in every language**: signatures checked, replays outside the allowed window refused, the event parsed — for the standard signature and each older one still in use.

### Swift (iOS and macOS)
- **Prebuilt from the release repository**: add `https://github.com/Goodneo-Solutions/sapp-releases` in Swift Package Manager; `SApp` and `SAppUI` come as prebuilt frameworks for iOS devices, the iOS simulator and macOS, and work with every Xcode from 16 on.

### Kotlin and Android
- **New coordinates**: `ca.superroute:sapp`, `ca.superroute:sapp-android` and `ca.superroute:sapp-ui-android` from the Maven repository `https://goodneo-solutions.github.io/sapp-releases/maven`, from 0.2.0 on. The JitPack coordinates stop with 0.2.0.

### Every package
- **Documentation and example apps**: package pages link to the sApp documentation on open.superroute.ca/developer/sapp, and the example apps, built against the published packages, are in the release repository.
- **npm**: packages no longer carry a provenance statement.

# 0.2.0

## UI kits

### SwiftUI (iOS and macOS), Jetpack Compose (Android), React Native, WPF and WinForms (Windows)
- **Every component of the Flutter and web kits on five more platforms**: sign-in, notifications, wallet, orders and pickups; order detail, tracking, notification preferences and account and privacy; the locker map and returns; the return form and shipping orders; the store and mall; group buy for buyers and group leaders; and the driver components. They behave as on Flutter and the web, in the same 14 languages and themed by the service provider's branding.
- **The app keeps control**: components raise events and never navigate; files, photos, the position, the scanner, card payments, sign-in in the app's browser, images (Compose) and the sMap map come from the app.
- **Example apps** for SwiftUI, Android, React Native, WPF and WinForms.

### Windows
- **One shared layer**: the WPF and WinForms kits draw the same view models (`Superroute.SApp.UI`), so they behave alike.

# 0.1.1

## SDK

### Every package
- **Published from the release workflow**: npm, pub.dev and NuGet packages are published by this repository's own release workflow when a version is tagged; npm packages carry a provenance statement linking each version to the build that made it.
- **Windows**: `Superroute.SApp` is on NuGet.

# 0.1.0

## SDK

### Core (JavaScript and Dart)
- **Sign-in**: the Superroute sign-in page with PKCE, Google or Apple ID tokens, and email and password for apps approved for it; tokens refresh before they expire, one refresh at a time, and a refused refresh ends the session.
- **Partner app API**: every operation of open.superroute.ca/app/v1 as one method, with the app key sent for calls before sign-in and the service provider filled in from the client.
- **GraphQL**: partner GraphQL queries with the signed-in token or the app key.
- **Open Platform**: every capability of open.superroute.ca/v1, with a short-lived token from the developer's backend or an API key on a server.
- **Errors and retries**: one error type with the server's code; repeatable calls retried on a lost connection and temporary server errors, writes never.
- **Theme tokens**: colours, type, radius and spacing from the service provider's branding.
- **Session changes**: apps are told when a session starts and when it ends.
- **Sign-in in an in-app browser**: the Superroute sign-in page in one call, with the answer checked against the request and a closed browser treated as cancelled.

### Swift (iOS and macOS)
- **The same core in Swift**: the three sign-in methods, refresh, sign-out, the partner app API and Open Platform capabilities, GraphQL, errors, retries and theme tokens, safe under strict concurrency.
- **Keychain token store**: tokens kept in the Keychain, readable on this device only after the first unlock.

### Kotlin and Android
- **The same core in Kotlin**: coroutines throughout, answers as JSON elements, the Custom Tab sign-in page, ID token and password sign-in, refresh, sign-out, GraphQL, errors, retries and theme tokens.
- **Keystore token store**: tokens encrypted with a key that never leaves the Android Keystore.

### C# and .NET
- **The same core in C#**: async throughout with cancellation, answers as JSON nodes, the sign-in page, ID token and password sign-in, refresh, sign-out, GraphQL, errors, retries and theme tokens.
- **Windows token store**: tokens kept in a file encrypted for the current Windows user.

### UI kit (Flutter)
- **Sign-in**: the Superroute sign-in page, Google and Apple, and email and password, each shown only when the service provider and the app allow it.
- **Notifications**: all or unread with the unread count, opening marks a notification read, mark all as read.
- **Wallet**: balance, available credit and spendable amount with two decimals and the currency, transactions, top-up.
- **Orders and pickups**: the person's orders, and parcels in lockers with their pickup codes and opening the door after confirming.
- **Branding and languages**: colours from the service provider's branding; every text in 14 languages.

### UI kit: lockers and returns (Flutter, web and React)
- **Locker map**: lockers near the person with distance, status and free compartments, search and filters for pickup, returns and free space, and the same lockers on the app's own sMap map, fitted to them; tapping a locker or its point selects it.
- **Returns**: the person's returns, cancelling one after confirming, and a way into starting a return.

### UI kit: orders, tracking, notifications and privacy (Flutter, web and React)
- **Order detail**: reference, status, total and creation date, with a way on to tracking.
- **Tracking**: look up a parcel by number or code, signed in or not, with its history newest first in the person's language, and more details after confirming the phone number or postcode.
- **Notification preferences**: channels and topics, each saved as soon as it is switched.
- **Account and privacy**: the sign-ins to the app with signing out the others, the privacy policy and terms, and an account closure request with a reason, after confirming.

### UI kit: returns, shipping, store and group buy (Flutter, web and React)
- **Return form**: return type, where it was bought, reason, pickup address or drop-off, label or barcode file from the app, and the fee before submitting.
- **Shipping order**: service, sender and recipient, packages in the service provider's units, custom fields and the estimate before ordering.
- **Store and mall**: store directory, store page, product detail with variants and options, cart and checkout; a card payment is handed to the app.
- **Group buy**: open group buys, a campaign with pickup or home delivery and the card hold handed to the app, the person's orders and marking one received.
- **Group leaders**: applying to lead and the pickup point, opening the group and sharing its link, starting a group buy from a plan, the groups the person leads, and marking a household collected after confirming.

### UI kit: driver apps (Flutter, web and React)
- **Routes and stops**: current and past routes, starting and ending delivery, and completing a stop with photos, a drawn signature and the delivery PIN, or with a failure reason.
- **Work**: duty and order offers, the order pool, route boards with seats, claims and bids, earnings, and finding an order by scanning or typing its reference.

### UI kit (web and React)
- **The same five components on the web**: sign-in, notifications, wallet, orders and pickups as elements that work in any web page, themed and in 14 languages, opening a door only after the person confirms.
- **React components** over them, taking the client from the provider and reporting events as props.

### React and React Native
- **Hooks**: the client in context, the session state, reads that follow their inputs, and writes started by the person.
- **Secure token store**: tokens kept in the phone's secure storage through the storage module the app already uses.
