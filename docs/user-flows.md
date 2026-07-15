# UrSkool — User Flow Diagrams

---

## Flow 1: Student Learning Flow

```mermaid
flowchart TD
    A([Student visits site]) --> B[/CoursesPage/ Browse courses]
    B --> C[/CoursePage/ View course details]

    C --> D{Logged in?}
    D -- No --> E[/LoginPage/ or /SignupPage/]
    E --> C

    D -- Yes --> F{Course free\nor has access?}

    F -- Paid & no access --> G[Add to cart]
    G --> H[/CartPage/ Review cart]
    H --> I[/CheckoutPage/ Payment]
    I --> J{Payment OK?}
    J -- Failed --> I
    J -- Success --> K[[System: enroll student]]
    K --> C

    F -- Free or enrolled --> L{Has prior progress?}
    L -- No --> M[/StudentPlayerPage/ First lesson]
    L -- Yes --> N[/StudentPlayerPage/ Resume lesson]

    M & N --> O{Lesson type}

    O -- text --> P[Read content]
    P --> Q[[System: mark completed]]
    Q --> Z

    O -- video --> R[Watch video]
    R --> Q

    O -- quiz --> S[Answer questions]
    S --> T{Submit quiz}
    T -- Score < pass% --> U[Show failure\n+ retry option]
    U --> S
    T -- Score ≥ pass% --> V[[System: mark quiz passed\nUnlock next lesson]]
    V --> Z

    O -- code / project --> W[Edit HTML / CSS / JS\nin workspace]
    W --> X[Run / validate code]
    X --> Y{Validation rules\npassed?}
    Y -- No --> AA[Show feedback\n+ hints]
    AA --> W
    Y -- Yes --> AB[[System: mark code correct\nUnlock next lesson]]
    AB --> Z

    Z{More lessons?}
    Z -- Yes --> AC[Navigate to next lesson]
    AC --> O
    Z -- No --> AD[[System: mark course completed]]
    AD --> AE[/CoursePage/ Show 100% + certificate button]
    AE --> AF([/CertificatePage/ Download / share certificate])
```

---

## Flow 4: Course Builder Flow (Admin)

```mermaid
flowchart TD
    A([Admin navigates to builder]) --> B{Mode?}

    B -- Create new\n/course-builder --> C[/CourseBuilderPage/ Empty form]
    B -- Edit existing\n/course-builder/:id --> D[[System: fetch course data]]
    D --> E[Pre-populate form fields]

    C & E --> F[Fill course details\ntitle · subtitle · instructor\ncategory · level · price · image · description]

    F --> G[Add section]
    G --> H[Set section title]
    H --> I[Add lesson to section]

    I --> J[Set lesson title · duration · type]
    J --> K{Lesson type?}

    K -- text --> L[Enter text content]
    K -- video --> M[Enter video URL\n+ optional content]
    K -- quiz --> N[Enter question content\nin markdown/HTML]
    K -- code --> O[Set language\n+ optional HTML / CSS / JS\nstarter code]
    K -- project --> O

    L & M & N & O --> P{More lessons\nin this section?}
    P -- Yes --> I
    P -- No --> Q{More sections?}
    Q -- Yes --> G
    Q -- No --> R[Click Save / Create]

    R --> S{Form valid?\ntitle + instructor present}
    S -- No --> T[Button disabled —\nfix required fields]
    T --> F

    S -- Yes --> U[[API: POST /courses\nor PUT /courses/:id]]
    U --> V{Request result}

    V -- Error --> W[Toast: error message]
    W --> R
    V -- Success --> X[Toast: success message]
    X --> Y([Redirect to /course/:id])
```
