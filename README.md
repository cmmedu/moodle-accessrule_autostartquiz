# Autostart Quiz Plugin

[Leer en Español](README_ES.md)

This plugin lets students enter specific quizzes immediately by toggling the “autostart” checkbox, eliminating extra clicks and smoothing the quiz experience.

![Enable autostart](images/checkbox.png)

## Key features
- Automatically begins the quiz as soon as the student accesses its page.
- Applies to quizzes that allow this access rule and can be enabled or disabled by the teacher at will.
- Ideal for quizzes that combine instructional content with questions but are not necessarily high-stakes evaluations.

## Building the plugin

1. Zip the `autostart` directory.
2. Upload the resulting `.zip` to Moodle as a plugin package.
3. Publish the ZIP as a release if it represents a stable version for easier version tracking.

## Installing on a Moodle site

1. Navigate to *Site administration > Plugins > Install plugins* and upload the `.zip` package.
2. Follow the installer prompts to complete the setup.

![Install](images/install.png)

## Development shortcut

Copy the `autostart` directory straight into `mod/quiz/accessrule/` in your Moodle installation. This avoids zipping while you are actively developing and testing the plugin.

## License

This project is available under the MIT License. See `LICENSE` for details.

