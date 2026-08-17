---
name: feature-controllers
description: |
  Write feature controllers for laravel routes. Use When adding or refactoring controllers, wiring routes, or reviewing shape in app so page rendering and mutation stay aligned with feature intent instead or REST resources.
---

# Feature Resources

## Purpose

Use one controller per features intent. A features controller is HTTP adapter: it validates input, reads request context, calls Acctions/Queries/Services and returns an HTTP response.
Avoid REST resources controllers that collect unrelated action because they share a model name.


## Workflow

1. Identify the user intent behind the route or form
2. Decide whether the route renders a page (`render`), executes a mutation (`execute`), or both
3. Create or update the smallest controller that owns that feature's intent
4. Keep public handler methods limited to `render` and/or `execute`
5. Move business logic to Actions and non-trivial reads to Projections

Done when every changed route maps to a controller whose public handlers match intent,
and the controller is the only one responsible for that feature.


## Handler Semantics

- Use `render` for `GET` routes that load projections and render.
- Use `execute` for `POST` `PUT` `PATCH` `DELETE` routes that perform one mutation intent.
- A controller may expose both `render` and `execute` when they are the read/write halves of the same simple feature.
- Split execution controllers when a page contains multiple independent forms or mutation intents


## Split Rule

Keep together:

```php
class ProfileSettingController extends Controller{
	public function render(): Response {
		return Inertia::render('/profile/settings')
	}
	
	public function execute(ProfileUpdateRequest $request): RedirectResponse {
		$payload = $request->validated();
		$this->updateProfileAction->execute($payload);

		return to_route('profile.settings')
	}

}
```

Split when one page hosts multiple independent mutations.

```php

class SettingController extends Controller {
	public function render(): Response {
		return Inertia::render('/profile/settings')
	}
}

```

```php
class UpdatePasswordController extends Controller {
    public function execute(ProfileUpdateRequest $request): RedirectResponse {
        $payload = $request->validated();
        $this->updateProfileAction->execute($payload);
    
        return to_route('profile.settings')
    }
}
```

## Placement And Naming

Place controllers under the relevant capability:

- `app/<capability>/Controllers/*.php`
- `app/<capability>/Controllers/<scope>/*.php`

Name files and classes after the feature intent:

- `profile_settinsgs_controller.php` -> `ProfileSettingController`
- `setting_controller.php` -> `SettingController`
- `update_password_controller.php` -> `UpdatePasswordController`

Prefer capability language over model CRUD language. For exemple, prefer `AcceptInvitationCrontoller` over `InvidationsController.update`.

## Boundaries

- Keep request validation in controller when it is only used by one controller.
- Extract a dedicated validator only when rules are shared across controllers or large enough to distract form the handler flow.
- Put writes and side effects in Action or focused services
- Put writes and side effects in Action in Queries or projections builders.
- Do not add public HTTP handler methods other than `render` and `execute`.

## Validator Pattern

Define controller-local validators as static members and call the from `execute`:

```php
class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
```

Move validation to `app/<capability>/Requests/` only when another controller needs the same validation or the schema obscure the controller's HTTP  flow.

## Route Wiring

Wire routes directly to the matching handler.

```ts

router.get('/settings', [SettingController, 'render'])
router.put('/settings/profile', [ProfileSettingController, 'execute'])
router.put('/settings/password', [UpdatePasswordController, 'execute'])

```

For a simple read/write features:

```ts

router.get('/settings', [SettingController, 'render'])
router.put('/settings/profile', [ProfileSettingController, 'execute'])

```

## Review Checklist

- Does the controllers represent one feature intent ?
- Are public handler limited to `render` and / or `execute` ?
- Does `render` only assemble data for rendering ?
- Is controller-local validation defined as a static validator ?
- Are independent forms split into independent execution controllers ?
- Is business logic properly encapsulated in Actions or services ?
