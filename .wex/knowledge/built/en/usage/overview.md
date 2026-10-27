## What it does

Keeps the repositories of GitHub and GitLab accounts and the commits of each day of their last year, and hands them to symfony-git as a `GitActivityProviderInterface`: what a page shows as « repositories » and « commits », and as a calendar (`git_activity()`, `chart('calendar', {}, { source: 'git-activity' })` — symfony-git-ds, symfony-charts-ds). Nothing is asked of a host when a page is drawn: the answers are kept in `remote_git_repository`, read again once `refresh_hours` old.

## Configuration

```yaml
wexample_symfony_remote_git:
  accounts:
    gitlab:
      host: gitlab
      base_url: 'https://gitlab.example.com/api/v4'
    github:
      host: github
      owner: example
      token: '%env(GITHUB_TOKEN)%'
  refresh_hours: 24
  auto_refresh: true
  refresh_batch: 3
```

- `host` is `github` or `gitlab`; `base_url` an instance of its own, the host's public API by default.
- `owner` is a GitHub user or organization, a GitLab group with its subgroups; without one, every repository the token sees — every public project of a GitLab instance without a token either.
- `token` is optional: GitLab lists its public projects to anyone, GitHub answers sixty requests an hour without one, too few for an organization (php-remote-git).

Each account's client is one of symfony-remote's clients, `git_<key>`: built from its definition, its status among the app's remotes (`remote:status`).

## A repository mirrored on two hosts

The accounts are read in their order, and a repository is known by its name across them: a mirror of a repository an earlier account holds is listed but never read, counted once, from the first. An instance mirroring its projects to GitHub is declared first, GitHub after it for what it holds alone.

## Keeping it up to date

After a response, at most once every five minutes, `refresh_batch` repositories are read again (`RemoteGitRefreshSubscriber`): an account's list once `refresh_hours` old, then the commits of its oldest repositories. A request's work stays short, and with some traffic every repository is read within the day. `auto_refresh: false` leaves it to a scheduler:

```bash
bin/console remote-git:refresh   # every stale repository at once
```

A host failing leaves what was read before, logged as a warning; GitHub still computing its statistics is asked again on the next pass. A repository gone from the host is no longer counted.

## Installing

The entity `RemoteGitRepository` needs its table: a migration of the application.
