# Setup Guide — run these skills in OpenCode (free)

This guide takes you from zero to a built WordPress plugin using the **free OpenCode** CLI and one of
the HTML → Elementor / Divi skills.

No paid subscription is required to run OpenCode itself. You only need a model to talk to — OpenCode
Zen offers free models, and you can also plug in any key you already have (or a local model).

---

## 1. Install OpenCode

Pick the install method for your OS. On Windows the recommended path is WSL for best compatibility,
but native installs work too.

### Windows

```powershell
# Option A — Chocolatey
choco install opencode

# Option B — Scoop
scoop install opencode

# Option C — npm (works everywhere Node is installed)
npm install -g opencode-ai
```

> Recommended: **WSL**. Inside WSL run the Linux install below — OpenCode's build tools (zip, PHP,
> headless Chrome) behave better on Linux than on PowerShell 5.1.

### macOS / Linux / WSL

```bash
curl -fsSL https://opencode.ai/install | bash

# or
npm install -g opencode-ai
brew install anomalyco/tap/opencode   # macOS / Linux
```

Verify:

```bash
opencode --version
```

---

## 2. Connect a model (free option)

OpenCode talks to any LLM provider. For the **free** route:

1. Start OpenCode in any folder:

   ```bash
   opencode
   ```

2. Run the connect command:

   ```
   /connect
   ```

3. Choose **opencode** (OpenCode Zen). Sign in at <https://opencode.ai/auth>, pick a **free model**
   if offered, and paste the key.

No key wanted? Connect a local model instead (e.g. **LM Studio** or **Ollama**) and select it with
`/models`. These skills only need a model good at tool-calling and code.
Select/switch models any time with:

```
/models
```

> Tip: these skills generate a lot of code. A stronger model (Claude, GPT, Gemini, MiniMax — or a
> free OpenCode Zen model) gives noticeably better results than a tiny local one.

---

## 3. Install the skills

OpenCode discovers skills from these locations (global = all projects):

| Scope | Path |
| --- | --- |
| Global | `~/.config/opencode/skills/<name>/SKILL.md` |
| Project | `.opencode/skills/<name>/SKILL.md` |
| Claude-compatible global | `~/.claude/skills/<name>/SKILL.md` |

Each skill is a folder containing a `SKILL.md`. Copy the three skill folders here.

### Windows (PowerShell)

```powershell
# from the repo root
$dest = "$env:USERPROFILE\.config\opencode\skills"
New-Item -ItemType Directory -Force -Path $dest | Out-Null
Copy-Item -Recurse -Force .\html-to-elementor,.\html-to-elementor-pro,.\html-to-divi $dest
```

### macOS / Linux / WSL

```bash
# from the repo root
mkdir -p ~/.config/opencode/skills
cp -r html-to-elementor html-to-elementor-pro html-to-divi ~/.config/opencode/skills/
```

### Alternative — keep it project-local

If you only want these skills for one project, copy them into that project's `.opencode/skills/`
folder instead:

```bash
mkdir -p .opencode/skills
cp -r /path/to/repo/html-to-elementor .opencode/skills/
```

The final layout must look like this (the folder name **must** match the `name:` in the frontmatter):

```
~/.config/opencode/skills/
├── html-to-elementor/SKILL.md
├── html-to-elementor-pro/SKILL.md
└── html-to-divi/SKILL.md
```

---

## 4. Confirm OpenCode can see them

Open OpenCode and check available skills. They should appear in the agent's skill list:

```bash
opencode
```

```
/skills
```

Or just ask:

```
What skills are available?
```

You should see `html-to-elementor`, `html-to-elementor-pro`, `html-to-divi`. Because each skill sets
`metadata: opencode/slash: "true"`, they are also exposed as slash commands:

```
/html-to-elementor
```

---

## 5. Run a build

1. Put your design source in a folder, e.g.:

   ```
   my-project/
   ├── final.html            ← the canonical page (Elementor skills)
   ├── assets/
   │   ├── css/
   │   ├── js/
   │   ├── images/
   │   └── fonts/
   └── (or a *.dc.html canvas for the Divi skill)
   ```

2. Start OpenCode in that folder:

   ```bash
   cd my-project
   opencode
   ```

3. Ask the agent to use the skill. Be explicit about the builder:

   ```
   Use the html-to-elementor skill. The source is final.html.
   Build the WordPress plugin. Brand slug is "acme".
   ```

   Or run the slash command:

   ```
   /html-to-elementor
   ```

4. **Answer the setup questions.** The agent will ask up front (this is by design — it wires the
   plugin correctly the first time):

   - brand / slug → plugin folder + widget prefix
   - GitHub repo (`owner/repo`) for the auto-updater
   - token constant name (for a private repo)
   - whether the target uses Elementor free vs Pro, and which form system
   - which pages to build

5. The agent generates the plugin, builds a per-section preview, and packages a `.zip`
   (forward-slash, via `git archive`).

---

## 6. Install on WordPress

1. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the generated `.zip` → **Install
   → Activate**.
2. Add the widgets/modules to a page (they appear under the plugin's category in Elementor / the
   module list in Divi 5).
3. For the Elementor skills, optionally import the provided `templates/<page>.json` via
   **Elementor → Templates → Import**, or use the registered item in **Saved Templates**.

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Skill doesn't appear | `SKILL.md` must be all-caps; frontmatter needs `name` + `description`; folder name must equal `name`. |
| "Plugin file does not exist." on install | The zip used backslash separators. Always package with `git archive` or Info-ZIP, never PowerShell `Compress-Archive`. |
| Styles don't match | The agent should **lift** values from source CSS, not re-derive. Re-run with the exact source CSS present. |
| Animations dead after install | Source class names/ids must be kept verbatim — the bundled JS targets them. Don't rename. |
| Private-repo updates not showing | Confirm the `*_GH_TOKEN` constant in `wp-config.php`; verify the release asset is named exactly `<slug>.zip`. |

More: [OpenCode docs → Skills](https://opencode.ai/docs/skills/) ·
[OpenCode install docs](https://opencode.ai/docs/)
