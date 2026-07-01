#!/bin/bash

# Distinguish " ", "\t" from "\n"
IFS=
dest_branch=dist
origin=$(git remote get-url origin)
src_branch=$(git rev-parse --abbrev-ref HEAD)
src_sha=$(git rev-parse --short HEAD)
message=
msg_suffix=

# Exit if no dest branch is found
if [[ ! $(git ls-remote --heads --exit-code "$origin" $dest_branch) ]]; then
  echo
  echo "No $dest_branch branch found, won't push."
  exit
fi

# Clone and configure dest branch
clone() {
  pushd dist || exit 1
  if [[ ! -d '.git' ]]; then
    git clone "$origin" -b $dest_branch --depth 1 --bare .git
    git config --unset core.bare
    git reset
  fi
  popd || exit 1
}

# If src branch is dirty, confirm push to dest branch
confirm_dirty() {
  if [[ $(git status --porcelain) ]]; then
    echo
    echo "Uncommitted changes found, push to $dest_branch anyway? [yN]"
    while read -rsn1 key; do
      case $key in
      '') exit ;;
      n)
        echo "$key"
        exit
        ;;
      y)
        echo "$key"
        break
        ;;
      esac
    done
    msg_suffix='(wip)'
    echo
  fi
}

# Build commit message with sha from src branch and changes since last dest branch push, set as $message
get_message() {
  local last_sha changes body
  pushd dist >/dev/null || exit 1
  last_sha=$(git log -1 --pretty=format:%s | cut -d' ' -f4)
  popd >/dev/null || exit 1
  changes=$([[ -n "$last_sha" ]] && git log "$last_sha".."$src_sha" --pretty=format:'%h  %s')
  body=$([[ -n "$changes" ]] && printf '\n\nChanges since %s:\n%s' "$last_sha" "$changes")
  message="build from $src_branch: $src_sha $msg_suffix $body"
}

# Push changes to dest branch
push() {
  confirm_dirty
  get_message
  pushd dist || exit 1
  if [[ $(git status --porcelain) ]]; then
    git commit -am "$message"
    git push origin $dest_branch
  else
    echo
    echo 'No changes to commit.'
    echo
  fi
  popd || exit 1
}

# Handle incorrect usage
usage() {
  echo "usage: $0 [-cp]"
  exit 2
}

# Read options
while getopts 'cp' option; do
  case $option in
  c) clone ;;
  p) push ;;
  ?) usage ;;
  esac
done

# Handle no options given
if [[ $OPTIND -eq 1 ]]; then
  usage
fi
