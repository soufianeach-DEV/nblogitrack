export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
            {/* Image de fond, sur toute la page */}
            <div
                className="absolute inset-0 scale-105 bg-cover bg-center blur-sm"
                style={{ backgroundImage: "url('/images/login-bg.jpg')" }}
            />
            {/* Le filtre, par-dessus le fond */}
            <div className="absolute inset-0 bg-marine-deep/70" />

            {/* La carte de connexion, au-dessus du fond */}
            <div className="relative z-10 flex w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div className="hidden w-1/2 flex-col justify-between bg-gradient-to-br from-marine to-marine-deep p-10 text-white md:flex">
                    <img src="/images/logo-blanc.png" alt="NBLogiTrack" className="w-32" />
                    <div>
                        <h2 className="text-3xl font-bold leading-tight">
                            Optimisez votre logistique B2B en toute confiance.
                        </h2>
                        <p className="mt-4 text-slate-300">
                            La plateforme de référence pour le suivi d'expéditions et la gestion de flotte en Belgique.
                        </p>
                    </div>
                    <div className="flex gap-10">
                        <div>
                            <div className="text-2xl font-bold text-action">1.2M+</div>
                            <div className="text-sm text-slate-400">Expéditions / an</div>
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-action">99.9%</div>
                            <div className="text-sm text-slate-400">Fiabilité</div>
                        </div>
                    </div>
                </div>

                <div className="w-full p-8 md:w-1/2 md:p-10">{children}</div>
            </div>
        </div>
    );
}