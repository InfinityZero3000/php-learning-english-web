import * as React from "react";
import { cn } from "@/lib/utils";
import { IconCheck, IconChevronDown } from "@tabler/icons-react";

export const Select = React.forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
  ({ className, children, value, onChange, ...props }, ref) => {
    const [open, setOpen] = React.useState(false);
    const containerRef = React.useRef<HTMLDivElement>(null);

    // Flatten children to extract <option> data
    const childArray = React.Children.toArray(children);
    const options: { value: string; label: React.ReactNode }[] = [];
    
    type OptionLike = React.ReactElement<{ value?: string | number; children?: React.ReactNode }>;
    const extractOptions = (node: React.ReactNode) => {
      if (!React.isValidElement(node)) return;
      const element = node as OptionLike;
      if (node.type === "option") {
        options.push({
          value: String(element.props.value !== undefined ? element.props.value : element.props.children),
          label: element.props.children,
        });
      } else if (element.props.children) {
        React.Children.toArray(element.props.children).forEach(extractOptions);
      }
    };
    childArray.forEach(extractOptions);

    const safeValue = value !== undefined && value !== null ? String(value) : undefined;
    const selectedLabel = options.find((o) => o.value === safeValue)?.label 
                          || options[0]?.label 
                          || "Select...";

    React.useEffect(() => {
      const clickOutside = (e: MouseEvent) => {
        if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
          setOpen(false);
        }
      };
      document.addEventListener("mousedown", clickOutside);
      return () => document.removeEventListener("mousedown", clickOutside);
    }, []);

    const handleSelect = (val: string) => {
      setOpen(false);
      if (onChange) {
        const event = {
          target: { value: val },
          currentTarget: { value: val },
          preventDefault: () => {},
          stopPropagation: () => {},
        } as unknown as React.ChangeEvent<HTMLSelectElement>;
        onChange(event);
      }
    };

    return (
      <div ref={containerRef} className={cn("relative", className)}>
        <select ref={ref} value={value} onChange={onChange} className="hidden" aria-hidden="true" {...props}>
          {children}
        </select>
        
        <button
          type="button"
          onClick={() => setOpen(!open)}
          className={cn(
            "flex h-11 w-full items-center justify-between rounded-xl border-2 bg-card px-3 text-sm font-medium outline-none transition",
            open ? "border-primary ring-4 ring-primary/10" : "border-input hover:border-muted-foreground/30"
          )}
        >
          <span className="truncate">{selectedLabel}</span>
          <IconChevronDown className={cn("h-4 w-4 shrink-0 text-muted-foreground transition-transform", open ? "rotate-180" : "")} />
        </button>

        {open && (
          <div className="absolute z-50 mt-2 max-h-60 w-full overflow-auto rounded-xl border-2 border-input bg-card p-1 shadow-lg ring-4 ring-primary/5 animate-in fade-in zoom-in-95">
            {options.map((opt, i) => (
              <button
                key={i}
                type="button"
                className={cn(
                  "relative flex w-full cursor-pointer select-none items-center rounded-lg py-2 pl-3 pr-9 text-sm font-medium outline-none transition-colors hover:bg-accent focus:bg-accent",
                  opt.value === safeValue ? "bg-primary/10 text-primary hover:bg-primary/20" : ""
                )}
                onClick={() => handleSelect(opt.value)}
              >
                <span className="truncate block w-full text-left">{opt.label}</span>
                {opt.value === safeValue && (
                  <span className="absolute right-3 flex items-center justify-center text-primary">
                    <IconCheck className="h-4 w-4" />
                  </span>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
    );
  }
);
Select.displayName = "Select";
