interface StatsCardProps {
  icon: string;
  label: string;
  value: string | number;
  color?: string;
}

export default function StatsCard({ icon, label, value, color = '#006590' }: StatsCardProps) {
  return (
    <div
      className="p-6 rounded-2xl flex items-center gap-4 cursor-default transition-all"
      style={{
        backgroundColor: '#ffffff',
        border: '2px solid #bdc8d2',
        borderBottom: '4px solid #bdc8d2',
      }}
      onMouseEnter={(e) => {
        const el = e.currentTarget as HTMLElement;
        el.style.borderColor = color;
        el.style.borderBottomColor = color;
      }}
      onMouseLeave={(e) => {
        const el = e.currentTarget as HTMLElement;
        el.style.borderColor = '#bdc8d2';
        el.style.borderBottomColor = '#bdc8d2';
      }}
    >
      <div className="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0"
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
