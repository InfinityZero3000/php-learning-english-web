interface StatsCardProps {
  icon: string;
  label: string;
  value: string | number;
  color?: string;
}

export default function StatsCard({ icon, label, value, color = '#006590' }: StatsCardProps) {
  return (
    <div
      className="learning-card group flex cursor-default items-center gap-4 p-5 sm:p-6"
      style={{
        backgroundColor: '#ffffff',
      }}
      onMouseEnter={(e) => {
        const el = e.currentTarget as HTMLElement;
        el.style.borderColor = color;
      }}
      onMouseLeave={(e) => {
        const el = e.currentTarget as HTMLElement;
        el.style.borderColor = '#bdc8d2';
      }}
    >
      <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl transition-transform group-hover:-translate-y-0.5"
        style={{ backgroundColor: color + '18' }}>
        <span className="material-symbols-outlined text-2xl" style={{ color }}>{icon}</span>
      </div>
      <div>
        <p className="text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{label}</p>
        <p className="text-2xl font-extrabold mt-0.5" style={{ color: '#1b1c1c' }}>{value}</p>
      </div>
    </div>
  );
}
