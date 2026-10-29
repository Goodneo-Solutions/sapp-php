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
