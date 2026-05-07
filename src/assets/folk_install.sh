#!/usr/bin/env bash
set -Eeuo pipefail

script_name="$0"
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
script_repo_dir="$(cd -- "$script_dir/.." && pwd)"

folk_user="folk"
repo_dir=""
repo_dir_explicit=0
repo_from_script=0
repo_url="https://github.com/FolkComputer/folk.git"
repo_ref=""
gpu_mode="auto"
hostname_name=""
install_avahi=1
install_ssh_server=0
install_printer=0
install_debug_tools=0
configure_service=1
start_service=1
build_folk=1
install_sudoers=1
dry_run=0
force_install=0
install_root="${FOLK_INSTALL_ROOT:-}"
folk_home="/home/$folk_user"
ui_mode="auto"
ui_enabled=0
ui_started=0
ui_lines=0
log_file=""
current_step_index=-1
step_count=0
step_labels=()
step_statuses=()
step_details=()

usage() {
  cat <<USAGE
Usage: sudo $script_name [options]

Install the Linux tabletop dependencies and service for Folk.

Options:
  --user USER             Folk system user to create/configure (default: folk)
  --repo-dir PATH         Folk checkout used by the systemd service
                          (default: checkout containing this script, or
                          /home/USER/folk for standalone downloaded scripts)
  --repo-url URL          Git URL to clone when the Folk checkout is missing
                          (default: https://github.com/FolkComputer/folk.git)
  --repo-ref REF          Optional branch, tag, or commit to check out
  --hostname NAME         Set the machine hostname with hostnamectl
  --gpu auto|mesa|nvidia|none
                          Install Vulkan driver support (default: auto)
  --mesa                  Shortcut for --gpu mesa
  --nvidia                Shortcut for --gpu nvidia
  --no-gpu                Shortcut for --gpu none
  --with-ssh-server       Install openssh-server
  --no-avahi              Do not install avahi-daemon
  --with-printer          Install CUPS and add USER to lpadmin
  --with-debug-tools      Install optional debugging/profiling packages
  --no-service            Do not install or enable folk.service
  --no-start              Install/enable folk.service, but do not start it
  --no-build              Do not run make deps && make, and do not start service
  --no-sudoers            Do not install passwordless systemctl sudoers rule
  --dry-run               Print the operations without changing the system
  -f, --force             Replace installer-managed files even if they exist
  --no-ui, --plain        Disable the interactive progress UI
  -h, --help              Show this help

Examples:
  sudo ./scripts/install-linux-tabletop.sh
  sudo ./scripts/install-linux-tabletop.sh --nvidia --with-printer
  sudo ./scripts/install-linux-tabletop.sh --hostname folk-table --with-ssh-server
USAGE
}

log() {
  if [[ "$ui_enabled" -eq 1 ]]; then
    printf '==> %s\n' "$*" >>"$log_file"
  else
    printf '==> %s\n' "$*"
  fi
}

warn() {
  if [[ "$ui_enabled" -eq 1 ]]; then
    printf 'warning: %s\n' "$*" >>"$log_file"
  else
    printf 'warning: %s\n' "$*" >&2
  fi
}

die() {
  if [[ "$ui_enabled" -eq 1 && "$current_step_index" -ge 0 ]]; then
    set_step_status "$current_step_index" "failed" "$*"
    finish_ui
  fi
  printf 'error: %s\n' "$*" >&2
  if [[ -n "$log_file" ]]; then
    printf 'log: %s\n' "$log_file" >&2
  fi
  exit 1
}

run() {
  if [[ "$dry_run" -eq 1 ]]; then
    if [[ "$ui_enabled" -eq 1 ]]; then
      printf '+' >>"$log_file"
      printf ' %q' "$@" >>"$log_file"
      printf '\n' >>"$log_file"
    else
      printf '+'
      printf ' %q' "$@"
      printf '\n'
    fi
  elif [[ "$ui_enabled" -eq 1 ]]; then
    "$@" >>"$log_file" 2>&1
  else
    "$@"
  fi
}

run_as_folk_user() {
  if [[ "$dry_run" -eq 1 ]]; then
    if [[ "$ui_enabled" -eq 1 ]]; then
      printf '+' >>"$log_file"
      if [[ "$(id -u)" -eq 0 ]]; then
        printf ' %q' sudo -H -u "$folk_user" >>"$log_file"
      fi
      printf ' %q' "$@" >>"$log_file"
      printf '\n' >>"$log_file"
    else
      printf '+'
      if [[ "$(id -u)" -eq 0 ]]; then
        printf ' %q' sudo -H -u "$folk_user"
      fi
      printf ' %q' "$@"
      printf '\n'
    fi
  elif [[ "$(id -u)" -eq 0 ]]; then
    if [[ "$ui_enabled" -eq 1 ]]; then
      sudo -H -u "$folk_user" "$@" >>"$log_file" 2>&1
    else
      sudo -H -u "$folk_user" "$@"
    fi
  elif [[ "$ui_enabled" -eq 1 ]]; then
    "$@" >>"$log_file" 2>&1
  else
    "$@"
  fi
}

install_from_stdin() {
  local path="$1"
  local mode="$2"
  local tmp

  tmp="$(mktemp)"
  cat >"$tmp"
  install_file_safely "$path" "$mode" "$tmp"
  rm -f "$tmp"
}

target_path() {
  local path="$1"

  if [[ -n "$install_root" && "$path" == /* ]]; then
    printf '%s%s\n' "${install_root%/}" "$path"
  else
    printf '%s\n' "$path"
  fi
}

absolute_path() {
  local path="$1"

  if [[ "$path" == /* ]]; then
    printf '%s\n' "$path"
  else
    printf '%s/%s\n' "$(pwd -P)" "$path"
  fi
}

is_folk_checkout() {
  local path="$1"

  [[ -f "$path/Makefile" && -f "$path/folk.c" ]]
}

resolve_repo_dir() {
  if [[ "$repo_dir_explicit" -eq 1 ]]; then
    repo_dir="$(absolute_path "$repo_dir")"
    return
  fi

  if is_folk_checkout "$script_repo_dir"; then
    repo_dir="$script_repo_dir"
    repo_from_script=1
    return
  fi

  repo_dir="/home/$folk_user/folk"
}

install_directory() {
  local path="$1"

  if [[ -d "$path" ]]; then
    return
  fi

  if [[ -n "$install_root" ]]; then
    run install -d -m 0755 "$path"
  else
    run install -d -o root -g root -m 0755 "$path"
  fi
}

install_file_safely() {
  local display_path="$1"
  local mode="$2"
  local source_path="$3"
  local destination_path
  local destination_dir

  destination_path="$(target_path "$display_path")"
  destination_dir="$(dirname -- "$destination_path")"

  if [[ -e "$destination_path" || -L "$destination_path" ]]; then
    if [[ "$force_install" -eq 1 ]]; then
      if [[ "$dry_run" -eq 1 ]]; then
        log "would remove and reinstall $display_path"
        return
      fi

      log "removing and reinstalling $display_path"
      run rm -rf -- "$destination_path"
    elif [[ ! -f "$destination_path" ]]; then
      die "refusing to overwrite non-file at $display_path"
    elif cmp -s "$source_path" "$destination_path"; then
      log "$display_path already matches; leaving it untouched"
      return
    else
      die "refusing to overwrite existing $display_path because its contents differ"
    fi
  fi

  if [[ "$dry_run" -eq 1 ]]; then
    log "would write $display_path"
    return
  fi

  install_directory "$destination_dir"
  if [[ -n "$install_root" ]]; then
    run install -m "$mode" "$source_path" "$destination_path"
  else
    run install -o root -g root -m "$mode" "$source_path" "$destination_path"
  fi
}

install_packages() {
  local packages=("$@")

  if [[ "${#packages[@]}" -eq 0 ]]; then
    return
  fi

  run env DEBIAN_FRONTEND=noninteractive apt-get install -y "${packages[@]}"
}

add_step() {
  step_labels[$step_count]="$1"
  step_statuses[$step_count]="pending"
  step_details[$step_count]=""
  step_count=$((step_count + 1))
}

status_marker() {
  case "$1" in
    pending) printf '....' ;;
    running) printf '>>>>' ;;
    done) printf 'DONE' ;;
    skipped) printf 'SKIP' ;;
    failed) printf 'FAIL' ;;
    *) printf '????' ;;
  esac
}

setup_log_file() {
  if [[ -n "$log_file" ]]; then
    return
  fi

  log_file="${TMPDIR:-/tmp}/folk-install-$(date +%Y%m%d-%H%M%S).log"
  : >"$log_file"
}

init_steps() {
  step_count=0
  step_labels=()
  step_statuses=()
  step_details=()

  STEP_APT_UPDATE=$step_count; add_step "Apt metadata"
  STEP_KEYMAP=$step_count; add_step "Keyboard policy"
  STEP_BASE_PACKAGES=$step_count; add_step "Core packages"
  STEP_NETWORK_PACKAGES=$step_count; add_step "Network access"
  STEP_PRINTER_PACKAGES=$step_count; add_step "Printer support"
  STEP_DEBUG_PACKAGES=$step_count; add_step "Debug tools"
  STEP_GPU=$step_count; add_step "Vulkan driver"
  STEP_HOSTNAME=$step_count; add_step "Hostname"
  STEP_USER=$step_count; add_step "Folk user"
  STEP_GROUPS=$step_count; add_step "Device groups"
  STEP_REPOSITORY=$step_count; add_step "Folk repository"
  STEP_UDEV=$step_count; add_step "Input devices"
  STEP_SUDOERS=$step_count; add_step "Service permissions"
  STEP_SERVICE=$step_count; add_step "Systemd service"
  STEP_BUILD=$step_count; add_step "Folk build"
  STEP_START=$step_count; add_step "Folk service start"
}

setup_ui() {
  if [[ "$ui_mode" != "plain" && -t 1 && "${TERM:-}" != "dumb" ]]; then
    setup_log_file
    ui_enabled=1
    render_ui
  fi
}

render_ui() {
  local i
  local status
  local marker
  local detail
  local current="idle"
  local lines=0

  if [[ "$ui_enabled" -ne 1 ]]; then
    return
  fi

  if [[ "$ui_started" -eq 1 ]]; then
    printf '\033[%sA' "$ui_lines"
  fi
  ui_started=1

  if [[ "$current_step_index" -ge 0 ]]; then
    current="${step_labels[$current_step_index]}"
  fi

  printf '\033[2KFolk tabletop setup\n'; lines=$((lines + 1))
  printf '\033[2KRepo: %s\n' "$repo_dir"; lines=$((lines + 1))
  printf '\033[2KUser: %s | GPU: %s | Log: %s\n' "$folk_user" "$gpu_mode" "$log_file"; lines=$((lines + 1))
  printf '\033[2K\n'; lines=$((lines + 1))

  for ((i = 0; i < step_count; i++)); do
    status="${step_statuses[$i]}"
    marker="$(status_marker "$status")"
    detail="${step_details[$i]}"
    if [[ -n "$detail" ]]; then
      printf '\033[2K[%s] %s - %s\n' "$marker" "${step_labels[$i]}" "$detail"
    else
      printf '\033[2K[%s] %s\n' "$marker" "${step_labels[$i]}"
    fi
    lines=$((lines + 1))
  done

  printf '\033[2K\n'; lines=$((lines + 1))
  printf '\033[2KCurrent: %s\n' "$current"; lines=$((lines + 1))
  ui_lines="$lines"
}

set_step_status() {
  local index="$1"
  local status="$2"
  local detail="${3:-}"

  step_statuses[$index]="$status"
  step_details[$index]="$detail"
  render_ui
}

finish_ui() {
  if [[ "$ui_enabled" -eq 1 ]]; then
    current_step_index=-1
    render_ui
    printf '\n'
    ui_enabled=0
  fi
}

run_step() {
  local index="$1"
  local detail="$2"
  local done_detail="$3"
  shift 3

  current_step_index="$index"
  set_step_status "$index" "running" "$detail"
  "$@"
  set_step_status "$index" "done" "$done_detail"
  current_step_index=-1
}

skip_step() {
  local index="$1"
  local detail="$2"

  current_step_index=-1
  set_step_status "$index" "skipped" "$detail"
}

on_error() {
  local exit_code="$?"
  local failed_step=""

  trap - ERR
  if [[ "$ui_enabled" -eq 1 && "$current_step_index" -ge 0 ]]; then
    failed_step="${step_labels[$current_step_index]}"
    set_step_status "$current_step_index" "failed" "see log"
    finish_ui
    printf 'Installation failed while running: %s\n' "$failed_step" >&2
    printf 'log: %s\n' "$log_file" >&2
  fi
  exit "$exit_code"
}

while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --user)
      [[ "$#" -ge 2 ]] || die "--user requires a value"
      folk_user="$2"
      shift 2
      ;;
    --repo-dir)
      [[ "$#" -ge 2 ]] || die "--repo-dir requires a value"
      repo_dir="$2"
      repo_dir_explicit=1
      shift 2
      ;;
    --repo-url)
      [[ "$#" -ge 2 ]] || die "--repo-url requires a value"
      repo_url="$2"
      shift 2
      ;;
    --repo-ref)
      [[ "$#" -ge 2 ]] || die "--repo-ref requires a value"
      repo_ref="$2"
      shift 2
      ;;
    --hostname)
      [[ "$#" -ge 2 ]] || die "--hostname requires a value"
      hostname_name="$2"
      shift 2
      ;;
    --gpu)
      [[ "$#" -ge 2 ]] || die "--gpu requires one of auto, mesa, nvidia, none"
      gpu_mode="$2"
      shift 2
      ;;
    --mesa)
      gpu_mode="mesa"
      shift
      ;;
    --nvidia)
      gpu_mode="nvidia"
      shift
      ;;
    --no-gpu)
      gpu_mode="none"
      shift
      ;;
    --with-ssh-server)
      install_ssh_server=1
      shift
      ;;
    --no-avahi)
      install_avahi=0
      shift
      ;;
    --with-printer)
      install_printer=1
      shift
      ;;
    --with-debug-tools)
      install_debug_tools=1
      shift
      ;;
    --no-service)
      configure_service=0
      start_service=0
      shift
      ;;
    --no-start)
      start_service=0
      shift
      ;;
    --no-build)
      build_folk=0
      start_service=0
      shift
      ;;
    --no-sudoers)
      install_sudoers=0
      shift
      ;;
    --dry-run)
      dry_run=1
      shift
      ;;
    -f | --force)
      force_install=1
      shift
      ;;
    --no-ui | --plain)
      ui_mode="plain"
      shift
      ;;
    -h | --help)
      usage
      exit 0
      ;;
    *)
      die "unknown option: $1"
      ;;
  esac
done

case "$gpu_mode" in
  auto | mesa | nvidia | none) ;;
  *) die "--gpu must be one of auto, mesa, nvidia, none" ;;
esac

folk_home="/home/$folk_user"

if [[ "$(id -u)" -ne 0 ]]; then
  die "run this script with sudo or as root"
fi

if [[ "$(uname -s)" != "Linux" ]]; then
  die "this installer is intended for Ubuntu Server or Raspberry Pi OS"
fi

command -v apt-get >/dev/null || die "apt-get is required"

resolve_repo_dir

if [[ "$repo_dir" =~ [[:space:]] ]]; then
  die "repo dir contains whitespace, which is not supported by the systemd unit: $repo_dir"
fi

base_packages=(
  apt-utils
  autoconf-archive
  automake
  build-essential
  cmake
  console-data
  git
  ghostscript
  glslc
  kbd
  libdrm-dev
  libgbm-dev
  libpng-dev
  libssl-dev
  libtool
  libturbojpeg0-dev
  libvulkan-dev
  libvulkan1
  meson
  pciutils
  pkg-config
  psmisc
  rsync
  sudo
  v4l-utils
  vulkan-tools
  vulkan-validationlayers
  zlib1g-dev
)

network_packages=()
if [[ "$install_avahi" -eq 1 ]]; then
  network_packages+=(avahi-daemon)
fi
if [[ "$install_ssh_server" -eq 1 ]]; then
  network_packages+=(openssh-server)
fi

folk_groups=(
  adm
  dialout
  cdrom
  sudo
  audio
  video
  plugdev
  games
  users
  input
  tty
  render
  netdev
  lpadmin
  gpio
  i2c
  spi
)

step_apt_update() {
  log "updating apt metadata"
  run apt-get update
}

step_keymap() {
  log "preseeding console-data to keep the kernel keymap"
  if [[ "$dry_run" -eq 1 ]]; then
    if [[ "$ui_enabled" -eq 1 ]]; then
      printf '+ debconf-set-selections <<< %q\n' 'console-data console-data/keymap/policy select Keep kernel keymap' >>"$log_file"
    else
      printf '+ debconf-set-selections <<< %q\n' 'console-data console-data/keymap/policy select Keep kernel keymap'
    fi
  else
    if [[ "$ui_enabled" -eq 1 ]]; then
      printf '%s\n' 'console-data console-data/keymap/policy select Keep kernel keymap' | debconf-set-selections >>"$log_file" 2>&1
    else
      printf '%s\n' 'console-data console-data/keymap/policy select Keep kernel keymap' | debconf-set-selections
    fi
  fi
}

step_base_packages() {
  log "installing core Folk dependencies"
  install_packages "${base_packages[@]}"
}

step_network_packages() {
  log "installing network discovery and access packages"
  install_packages "${network_packages[@]}"
}

step_printer_packages() {
  log "installing CUPS printer packages"
  install_packages cups cups-bsd
}

step_debug_packages() {
  log "installing optional debug tools"
  install_packages elfutils google-perftools libgoogle-perftools-dev
}

step_gpu_driver() {
  resolved_gpu="$gpu_mode"
  if [[ "$resolved_gpu" == "auto" ]]; then
    if command -v lspci >/dev/null && lspci | grep -qi 'nvidia'; then
      resolved_gpu="nvidia"
    else
      resolved_gpu="mesa"
    fi
  fi

  case "$resolved_gpu" in
    mesa)
      set_step_status "$STEP_GPU" "running" "installing Mesa Vulkan"
      log "installing Mesa Vulkan driver"
      install_packages mesa-vulkan-drivers
      ;;
    nvidia)
      set_step_status "$STEP_GPU" "running" "installing NVIDIA 580"
      log "installing NVIDIA driver 580"
      install_packages ubuntu-drivers-common
      run ubuntu-drivers install nvidia:580
      ;;
  esac
}

step_hostname() {
  command -v hostnamectl >/dev/null || die "hostnamectl is required to set hostname"
  log "setting hostname to $hostname_name"
  run hostnamectl set-hostname "$hostname_name"
}

step_user() {
  if ! id "$folk_user" >/dev/null 2>&1; then
    log "creating user $folk_user"
    run useradd -m "$folk_user"
    warn "set a password for $folk_user after installation with: sudo passwd $folk_user"
  else
    log "user $folk_user already exists"
  fi

  if [[ "$dry_run" -eq 0 ]]; then
    folk_home="$(getent passwd "$folk_user" | cut -d: -f6)"
  fi
  [[ -n "$folk_home" ]] || die "could not determine home directory for $folk_user"
}

step_groups() {
  local group
  local missing_groups=()

  for group in "${folk_groups[@]}"; do
    if getent group "$group" >/dev/null; then
      run usermod -a -G "$group" "$folk_user"
    else
      missing_groups+=("$group")
    fi
  done

  if [[ "${#missing_groups[@]}" -gt 0 ]]; then
    warn "skipped missing groups: ${missing_groups[*]}"
  fi
  run groups "$folk_user"
}

step_repository() {
  local parent_dir

  if [[ "$repo_dir_explicit" -eq 0 && "$repo_from_script" -eq 0 ]]; then
    repo_dir="$folk_home/folk"
    if [[ "$repo_dir" =~ [[:space:]] ]]; then
      die "repo dir contains whitespace, which is not supported by the systemd unit: $repo_dir"
    fi
    set_step_status "$STEP_REPOSITORY" "running" "using $repo_dir"
  fi

  if [[ "$force_install" -eq 1 && "$repo_from_script" -eq 0 && ( -e "$repo_dir" || -L "$repo_dir" ) ]]; then
    if [[ "$dry_run" -eq 1 ]]; then
      log "would remove and reclone $repo_dir"
    else
      log "removing and recloning $repo_dir"
      run rm -rf -- "$repo_dir"
    fi
  fi

  if is_folk_checkout "$repo_dir"; then
    log "using existing Folk checkout at $repo_dir"
    if [[ -n "$repo_ref" ]]; then
      log "checking out $repo_ref"
      run git -C "$repo_dir" fetch --tags --prune
      run git -C "$repo_dir" checkout "$repo_ref"
    fi
    return
  fi

  if [[ -e "$repo_dir" || -L "$repo_dir" ]]; then
    die "refusing to overwrite existing non-Folk repository path $repo_dir; rerun with --force to replace it"
  fi

  parent_dir="$(dirname -- "$repo_dir")"
  log "cloning Folk from $repo_url into $repo_dir"
  run install -d -m 0755 "$parent_dir"
  run git clone "$repo_url" "$repo_dir"
  if [[ -n "$repo_ref" ]]; then
    run git -C "$repo_dir" checkout "$repo_ref"
  fi
  run chown -R "$folk_user:" "$repo_dir"

  if [[ "$dry_run" -eq 0 ]] && ! is_folk_checkout "$repo_dir"; then
    die "cloned repository does not look like a Folk checkout: $repo_dir"
  fi
}

step_udev() {
  log "installing input udev rule"
  install_from_stdin /etc/udev/rules.d/99-input.rules 0644 <<'RULE'
SUBSYSTEM=="input", GROUP="input", MODE="0666"
RULE

  if command -v udevadm >/dev/null; then
    run udevadm control --reload-rules
    run udevadm trigger
  else
    warn "udevadm not found; reboot or reload udev rules manually"
  fi
}

step_sudoers() {
  local sudoers_tmp

  log "installing passwordless systemctl sudoers rule"
  sudoers_tmp="$(mktemp)"
  printf '%s ALL=(ALL) NOPASSWD: /usr/bin/systemctl\n' "$folk_user" >"$sudoers_tmp"
  if [[ "$dry_run" -eq 0 ]]; then
    visudo -cf "$sudoers_tmp" >/dev/null
  fi
  install_file_safely /etc/sudoers.d/folk-systemctl 0440 "$sudoers_tmp"
  rm -f "$sudoers_tmp"
}

step_service() {
  command -v systemctl >/dev/null || die "systemctl is required to install the service"

  make_path="$(command -v make || true)"
  [[ -n "$make_path" ]] || die "make is required"

  log "installing folk.service"
  install_from_stdin /etc/systemd/system/folk.service 0644 <<UNIT
[Unit]
Description=Folk service
After=network.target
StartLimitIntervalSec=0

[Service]
Type=simple
Restart=always
RestartSec=1
User=$folk_user
Environment=HOME=$folk_home
WorkingDirectory=$repo_dir
ExecStart=$make_path -C $repo_dir start

[Install]
WantedBy=multi-user.target
UNIT

  run systemctl daemon-reload
  run systemctl enable folk.service
}

step_build() {
  if [[ "$dry_run" -eq 0 ]] && ! sudo -H -u "$folk_user" test -w "$repo_dir"; then
    die "$repo_dir is not writable by $folk_user; adjust ownership or rerun with --no-build --no-start"
  fi

  log "building Folk as $folk_user"
  run_as_folk_user make -C "$repo_dir" deps
  run_as_folk_user make -C "$repo_dir"
}

step_start_service() {
  log "starting folk.service"
  if [[ "$dry_run" -eq 1 ]]; then
    printf '+ systemctl start-or-restart folk.service\n' >>"${log_file:-/dev/stdout}"
  elif systemctl is-active --quiet folk.service; then
    run systemctl restart folk.service
  else
    run systemctl start folk.service
  fi
}

init_steps
setup_ui
trap on_error ERR

run_step "$STEP_APT_UPDATE" "apt-get update" "metadata ready" step_apt_update

if command -v debconf-set-selections >/dev/null; then
  run_step "$STEP_KEYMAP" "console-data policy" "kernel keymap kept" step_keymap
else
  skip_step "$STEP_KEYMAP" "debconf-set-selections unavailable"
fi

run_step "$STEP_BASE_PACKAGES" "apt install core packages" "packages installed" step_base_packages

if [[ "${#network_packages[@]}" -gt 0 ]]; then
  run_step "$STEP_NETWORK_PACKAGES" "apt install ${network_packages[*]}" "network ready" step_network_packages
else
  skip_step "$STEP_NETWORK_PACKAGES" "not requested"
fi

if [[ "$install_printer" -eq 1 ]]; then
  run_step "$STEP_PRINTER_PACKAGES" "apt install CUPS" "printer packages installed" step_printer_packages
else
  skip_step "$STEP_PRINTER_PACKAGES" "not requested"
fi

if [[ "$install_debug_tools" -eq 1 ]]; then
  run_step "$STEP_DEBUG_PACKAGES" "apt install profiling tools" "debug tools installed" step_debug_packages
else
  skip_step "$STEP_DEBUG_PACKAGES" "not requested"
fi

if [[ "$gpu_mode" == "none" ]]; then
  skip_step "$STEP_GPU" "disabled by --no-gpu"
else
  run_step "$STEP_GPU" "detecting GPU" "driver installed" step_gpu_driver
fi

if [[ -n "$hostname_name" ]]; then
  run_step "$STEP_HOSTNAME" "hostnamectl set-hostname" "hostname set" step_hostname
else
  skip_step "$STEP_HOSTNAME" "not requested"
fi

run_step "$STEP_USER" "create or reuse $folk_user" "user ready" step_user
run_step "$STEP_GROUPS" "add $folk_user to device groups" "groups ready" step_groups
run_step "$STEP_REPOSITORY" "clone or reuse $repo_dir" "repository ready" step_repository
run_step "$STEP_UDEV" "install input rule" "input rule installed" step_udev

if [[ "$install_sudoers" -eq 1 ]]; then
  run_step "$STEP_SUDOERS" "allow systemctl without password" "sudoers rule installed" step_sudoers
else
  skip_step "$STEP_SUDOERS" "disabled by --no-sudoers"
fi

if [[ "$configure_service" -eq 1 ]]; then
  run_step "$STEP_SERVICE" "write and enable folk.service" "service enabled" step_service
else
  skip_step "$STEP_SERVICE" "disabled by --no-service"
fi

if [[ "$build_folk" -eq 1 ]]; then
  run_step "$STEP_BUILD" "make deps && make" "build complete" step_build
else
  skip_step "$STEP_BUILD" "disabled by --no-build"
fi

if [[ "$configure_service" -eq 1 && "$start_service" -eq 1 ]]; then
  run_step "$STEP_START" "start or restart folk.service" "service running" step_start_service
else
  skip_step "$STEP_START" "not requested"
fi

finish_ui

log "Folk tabletop installation complete"
if [[ "$configure_service" -eq 1 ]]; then
  log "service logs: journalctl -f -u folk"
fi
if [[ "$install_printer" -eq 1 ]]; then
  log "CUPS web UI is available locally on the tabletop at http://localhost:631"
fi

