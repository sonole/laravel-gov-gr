@once
    <style>
        .gg-taxisnet-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            line-height: 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .gg-taxisnet-btn:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .gg-taxisnet-btn svg {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }

        .gg-taxisnet-btn--login {
            background-color: #0b4ea2;
            border-color: #0b4ea2;
            color: #ffffff;
        }

        .gg-taxisnet-btn--login:hover {
            background-color: #0a4489;
        }

        .gg-taxisnet-btn--logout {
            background-color: transparent;
            border-color: #d1d5db;
            color: #374151;
        }

        .gg-taxisnet-btn--logout:hover {
            background-color: #f3f4f6;
        }

        /* OS-level dark mode, no cooperation needed from the host app. */
        @media (prefers-color-scheme: dark) {
            .gg-taxisnet-btn--login {
                background-color: #2563eb;
                border-color: #2563eb;
            }

            .gg-taxisnet-btn--login:hover {
                background-color: #1d4ed8;
            }

            .gg-taxisnet-btn--logout {
                border-color: #4b5563;
                color: #e5e7eb;
            }

            .gg-taxisnet-btn--logout:hover {
                background-color: #1f2937;
            }
        }

        /* Apps with their own manual dark-mode toggle (a "dark" class or data-theme
           attribute on <html>) win over the OS setting above. */
        html.dark .gg-taxisnet-btn--login,
        html[data-theme="dark"] .gg-taxisnet-btn--login {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        html.dark .gg-taxisnet-btn--login:hover,
        html[data-theme="dark"] .gg-taxisnet-btn--login:hover {
            background-color: #1d4ed8;
        }

        html.dark .gg-taxisnet-btn--logout,
        html[data-theme="dark"] .gg-taxisnet-btn--logout {
            border-color: #4b5563;
            color: #e5e7eb;
        }

        html.dark .gg-taxisnet-btn--logout:hover,
        html[data-theme="dark"] .gg-taxisnet-btn--logout:hover {
            background-color: #1f2937;
        }

        html.light .gg-taxisnet-btn--login,
        html[data-theme="light"] .gg-taxisnet-btn--login {
            background-color: #0b4ea2;
            border-color: #0b4ea2;
        }

        html.light .gg-taxisnet-btn--logout,
        html[data-theme="light"] .gg-taxisnet-btn--logout {
            border-color: #d1d5db;
            color: #374151;
        }
    </style>
@endonce
