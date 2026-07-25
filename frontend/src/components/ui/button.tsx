import * as React from "react";
import { cn } from "@/lib/utils";

type ButtonVariant = "default" | "secondary" | "outline" | "ghost" | "destructive";
type ButtonSize = "sm" | "md" | "lg" | "icon";

const variants: Record<ButtonVariant, string> = {
  default: "bg-primary text-primary-foreground hover:bg-primary/90 btn-press",
  secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/90",
  outline: "border-2 border-border bg-card text-foreground hover:border-primary hover:bg-accent btn-press-outline",
  ghost: "text-muted-foreground hover:bg-muted hover:text-foreground",
  destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90"
};

const sizes: Record<ButtonSize, string> = {
  sm: "h-9 px-3 text-xs",
  md: "h-11 px-4 text-sm",
  lg: "h-12 px-6 text-base",
  icon: "h-10 w-10 p-0"
};

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  asChildCompat?: "a";
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = "default", size = "md", asChildCompat, children, ...props }, ref) => {
    const classNames = cn(
        "inline-flex items-center justify-center gap-2 rounded-xl font-display font-bold uppercase tracking-[0.05em] transition disabled:pointer-events-none disabled:opacity-50",
        variants[variant],
        sizes[size],
        className
      );

    if (asChildCompat === "a" && React.isValidElement(children)) {
      return React.cloneElement(children as React.ReactElement<{ className?: string }>, {
        className: cn(classNames, (children.props as { className?: string }).className)
      }) as React.ReactElement;
    }

    return <button ref={ref} className={classNames} {...props}>{children}</button>;
  }
);
Button.displayName = "Button";
