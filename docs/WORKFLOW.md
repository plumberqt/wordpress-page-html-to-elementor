# Workflow — Process Graphs

Visual process graphs for each skill. (Rendered as Mermaid diagrams on GitHub.)

---

## Master workflow

```mermaid
flowchart TD
    A["Design source folder"] --> B{"Target builder?"}

    B -->|Elementor| E0
    B -->|Divi 5| D0

    subgraph E["html-to-elementor / -pro"]
        E0["0 · Orient<br/>find final.html, ignore variants"] --> E1["1 · Inventory sections<br/>(incl. id-less)"]
        E1 --> E2["2 · Extract VERBATIM<br/>markup · CSS · keyframes · JS · icons · tokens"]
        E2 --> E3["3 · Bundle assets<br/>images + fonts"]
        E3 --> E4["4 · Build plugin<br/>1 editable widget / section<br/>repeaters + style controls"]
        E4 --> E5["5 · Verify per-section preview"]
        E5 --> E6["6 · Package forward-slash zip"]
    end

    subgraph D["html-to-divi"]
        D0["0 · Ask setup<br/>export · version · repo · forms"] --> D1["1 · Extract design system<br/>tokens.css · sections.css"]
        D1 --> D2["2 · Plan module split<br/>parent + child"]
        D2 --> D3["3 · Generate plugin<br/>PHP render + JSX edit"]
        D3 --> D4["4 · Compile layout<br/>block markup + et_pb_layout JSON"]
        D4 --> D5["5 · Verify + VB checklist"]
        D5 --> D6["6 · Extra pages = content JSON"]
    end

    E6 --> W["Install .zip on WordPress"]
    D4 --> W
    W --> X["Import layout / template"]
    X --> Y["Edit content in Visual Builder"]
```

---

## Elementor skill — detailed

```mermaid
flowchart TD
    S(["final.html + assets"]) --> Q["Ask setup UP FRONT"]
    Q --> Q1["brand / slug"]
    Q --> Q2["GitHub repo owner/repo"]
    Q --> Q3["token constant name"]
    Q --> Q4["Elementor free or Pro?"]
    Q --> Q5["Forms: CF7 / Elementor Pro?"]

    Q1 & Q2 & Q3 & Q4 & Q5 --> SPLIT["Split page into sections"]
    SPLIT --> LOOP{"For each section"}

    LOOP --> L1["Markup + inline styles"]
    LOOP --> L2["class CSS + :hover + transitions"]
    LOOP --> L3["@keyframes"]
    LOOP --> L4["driving JS (sliders, reveal, hover)"]
    LOOP --> L5["real SVG icon paths"]
    LOOP --> L6["design tokens"]

    L1 & L2 & L3 & L4 & L5 & L6 --> WID["1 editable widget per section"]
    WID --> ISO["Theme isolation CSS block"]
    ISO --> CTRL["Controls + repeaters"]
    CTRL --> PREV["Preview + compare vs source"]
    PREV --> ZIP["git archive .zip"]
    ZIP --> DONE(["Install → import template → edit"])
```

---

## Divi skill — detailed

```mermaid
flowchart TD
    S(["Design: HTML / .dc.html / folder"]) --> Q["Ask setup UP FRONT"]
    Q --> Q1["brand / slug"]
    Q --> Q2["export from target install (ground truth)"]
    Q --> Q3["Divi version + theme builder?"]
    Q --> Q4["GitHub repo de + token"]
    Q --> Q5["forms export"]
    Q --> Q6["which pages?"]

    Q1 & Q2 & Q3 & Q4 & Q5 & Q6 --> DS["Extract design system<br/>tokens.css + sections.css + style.css"]
    DS --> RESET["Container reset + theme isolation"]
    RESET --> PLAN["Plan module split (table)<br/>parent + child for repeats"]
    PLAN --> GEN["Generate plugin from reference/<br/>module spec · PHP render · JSX edit"]
    GEN --> COMP["build-layout.js<br/>→ block markup + et_pb_layout JSON"]
    COMP --> VER["Verify (PHP harness, headless Chrome)<br/>+ Visual Builder checklist"]
    VER --> ZIP2["Package .zip + layout JSON"]
    ZIP2 --> DONE2(["Install → load layout onto page"])
    DONE2 --> MORE["New page = content JSON + compile"]
    MORE --> DONE2
```

---

## Auto-updater flow (both stacks)

```mermaid
sequenceDiagram
    participant Dev as Developer
    participant GH as GitHub repo
    participant WP as WordPress site

    Dev->>GH: push code
    Dev->>GH: tag vX.Y.Z
    Dev->>GH: attach release asset <slug>.zip
    WP->>GH: check Update URI (owner/repo)
    GH-->>WP: newer version available
    WP->>Dev: shows "Update now"
    WP->>GH: authenticated download (private repo)
    GH-->>WP: signed redirect → download zip
    WP->>WP: install + activate new version
```

> The asset must be named **exactly `<slug>.zip`** — versioned filenames break the updater
> (HTTP 415 on the zipball fallback).

---

## Packaging decision

```mermaid
flowchart LR
    A["Need a .zip for WordPress"] --> B{"Platform?"}
    B -->|Any| C["git archive --format=zip<br/>--prefix -o dist/name.zip HEAD"]
    B -->|Files uncommitted / Linux| D["zip -r -q name.zip name"]
    B -->|PowerShell 5.1| E["❌ Compress-Archive<br/>writes backslashes → broken install"]
```
