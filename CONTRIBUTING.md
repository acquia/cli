# Contributing

## General guidelines

- Issues filed directly with this project aren’t subject to a service-level agreement (SLA).
- The project maintainers are under no obligation to respond to support requests, feature requests, or pull requests.
- If more information is requested and no reply is received within a week, issues may be closed.

Newly filed issues will be reviewed by a maintainer and added to the backlog milestone if accepted.

Acquia doesn’t publish timelines or road maps to reflect when individual issues will be addressed. If you would like to request prioritization of a specific ticket, complete the following tasks:

- Submit product feedback through your Technical Account Manager or [submit a support ticket](https://docs.acquia.com/support/#contact-acquia-support) on your Cloud subscription.
- Vote for the relevant issue by adding a +1 reaction.
- Submit a pull request, which will receive priority review.

## Submitting issues

Before submitting an issue, be sure to search for existing issues (including closed issues) matching your issue. Duplicate issues will be closed.

Take care when selecting your issue type, and if you aren’t sure of the issue type, consider submitting a support request.

- Feature request: A request for a specific enhancement for this project. A feature request is distinct from a bug report because it indicates a missing feature for this project instead of a literal error with this project. Feature requests are distinct from support requests because they’re specific and atomic requests for new this project features, instead of a general request for help or guidance.

- Bug report: A defined instance of this project not behaving as expected. A bug report is distinct from a feature request because it represents a mismatch between what this project does and what this project claims to do. A bug report is distinct from a support request by including specific steps to reproduce the problem (ideally starting from a fresh installation of this project) and justifying why the instance is a problem with this project rather than with an underlying tool, such as Composer or Drush.

- Support request: A request for help or guidance. Use the issue type if you aren’t sure how to do something or can’t find a solution to a problem that may or may not be a bug. Before filing a support request, review documentation for solutions to common problems and general troubleshooting techniques.

If you have an Acquia subscription, consider filing a support ticket instead of an issue to receive support subject to your SLA.

After selecting your issue type, be sure to complete the entire issue template.

## Submitting pull requests

Pull requests must also adhere to the following guidelines:

- Pull requests must be atomic and targeted at a single issue rather than broad scope.
- Pull requests must contain clear testing steps and justification, and all other information required by the pull request template.
- Pull requests must pass automated tests before they will be reviewed. Acquia recommends running the tests locally before submitting.
- Pull requests must meet Drupal coding standards and best practices as defined by the project maintainers.

## Building and testing

To update the Acquia Cloud API spec, run:

```
composer update-cloud-api-spec
```
