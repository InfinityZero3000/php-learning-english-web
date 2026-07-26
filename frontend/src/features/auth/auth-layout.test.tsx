import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { AuthLayout } from "./auth-layout";
vi.mock("next/image", () => ({ default: ({ fill: _fill, priority: _priority, alt = "", ...props }: React.ComponentProps<"img"> & { fill?: boolean; priority?: boolean }) => <img alt={alt} {...props} /> }));
vi.mock("next/link", () => ({ default: (props: React.ComponentProps<"a">) => <a {...props} /> }));
describe("AuthLayout", () => { it("labels form content and hides its decorative image", () => { render(<AuthLayout title="Welcome" description="Continue"><form>Form</form></AuthLayout>); expect(screen.getByRole("region", { name: "Welcome" })).toBeInTheDocument(); expect(screen.getByRole("presentation", { hidden: true })).toHaveAttribute("alt", ""); }); });
