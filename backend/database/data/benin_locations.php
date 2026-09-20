<?php

/**
 * Découpage administratif du Bénin : 12 départements, 77 communes, 546
 * arrondissements. Source : paquet npm `decoupage-territorial-benin` (MIT,
 * https://github.com/Goldy98/decoupage-territorial-benin), croisé commune par
 * commune avec la liste INSAE/Wikipédia (mêmes effectifs). Noms mis en casse
 * titre, sans accents. Les quartiers ne sont pas repris (texte libre côté
 * profil — CLAUDE.md §5, ajout v0.19).
 */
return [
    ['name' => 'Alibori', 'communes' => [
        ['name' => 'Banikoara', 'arrondissements' => ['Founougo', 'Gomparou', 'Goumori', 'Kokey', 'Kokiborou', 'Ounet', 'Somperoukou', 'Soroko', 'Toura', 'Banikoara']],
        ['name' => 'Gogounou', 'arrondissements' => ['Bagou', 'Gounarou', 'Sori', 'Sougou-Kpan-Trossi', 'Wara', 'Gogounou']],
        ['name' => 'Kandi', 'arrondissements' => ['Angaradebou', 'Bensekou', 'Donwari', 'Kassakou', 'Saah', 'Sam', 'Sonsoro', 'Kandi 1', 'Kandi 2', 'Kandi 3']],
        ['name' => 'Karimama', 'arrondissements' => ['Birni Lafia', 'Bogo-Bogo', 'Kompa', 'Monsey', 'Karimama']],
        ['name' => 'Malanville', 'arrondissements' => ['Garou', 'Guene', 'Madecali', 'Toumboutou', 'Malanville']],
        ['name' => 'Segbana', 'arrondissements' => ['Libante', 'Liboussou', 'Lougou', 'Sokotindji', 'Segbana']],
    ]],
    ['name' => 'Atacora', 'communes' => [
        ['name' => 'Boukoumbe', 'arrondissements' => ['Dipoli', 'Korontiere', 'Koussoucoingou', 'Manta', 'Nata', 'Tabota', 'Boukoumbe']],
        ['name' => 'Cobly', 'arrondissements' => ['Datori', 'Kountori', 'Tapoga', 'Cobly']],
        ['name' => 'Kerou', 'arrondissements' => ['Brignamaro', 'Firou', 'Kaobagou', 'Kerou']],
        ['name' => 'Kouande', 'arrondissements' => ['Birni', 'Chabi-Couma', 'Foo-Tance', 'Guilmaro', 'Oroukayo', 'Kouande']],
        ['name' => 'Materi', 'arrondissements' => ['Dassari', 'Gouande', 'Nodi', 'Tantega', 'Tchanhouncossi', 'Materi']],
        ['name' => 'Natitingou', 'arrondissements' => ['Kotopounga', 'Kouaba', 'Kouandata', 'Perma', 'Tchoumi-Tchoumi', 'Natitingou I', 'Natitingou Ii', 'Natitingou Iii', 'Peporiyakou']],
        ['name' => 'Ouassa-Pehunco', 'arrondissements' => ['Gnemasson', 'Tobre', 'Pehunco']],
        ['name' => 'Tanguieta', 'arrondissements' => ['Cotiakou', 'N\'Dahonta', 'Taiacou', 'Tanongou', 'Tanguieta']],
        ['name' => 'Toukountouna', 'arrondissements' => ['Kouarfa', 'Tampegre', 'Toukountouna']],
    ]],
    ['name' => 'Atlantique', 'communes' => [
        ['name' => 'Abomey-Calavi', 'arrondissements' => ['Akassato', 'Godomey', 'Golo-Djigbe', 'Hevie', 'Kpanroun', 'Ouedo', 'Togba', 'Zinvie', 'Abomey-Calavi']],
        ['name' => 'Allada', 'arrondissements' => ['Agbanou', 'Ahouannonzoun', 'Attogon', 'Avakpa', 'Ayou', 'Hinvi', 'Lissegazoun', 'Lon-Agonmey', 'Sekou', 'Tokpa', 'Allada Centre', 'Togoudo']],
        ['name' => 'Kpomasse', 'arrondissements' => ['Aganmalome', 'Agbanto', 'Agonkanme', 'Dedome', 'Dekanme', 'Segbeya', 'Segbohoue', 'Tokpa-Dome', 'Kpomasse Centre']],
        ['name' => 'Ouidah', 'arrondissements' => ['Avlekete', 'Djegbadji', 'Gakpe', 'Houakpe-Daho', 'Pahou', 'Savi', 'Ouidah I', 'Ouidah Ii', 'Ouidah Iii', 'Ouidah Iv']],
        ['name' => 'So-Ava', 'arrondissements' => ['Ahomey-Lokpo', 'Dekanmey', 'Ganvie 1', 'Ganvie 2', 'Houedo-Aguekon', 'Vekky', 'So-Ava']],
        ['name' => 'Toffo', 'arrondissements' => ['Ague', 'Colli', 'Coussi', 'Dame', 'Djanglanme', 'Houegbo', 'Kpome', 'Sehoue', 'Sey', 'Toffo']],
        ['name' => 'Tori-Bossito', 'arrondissements' => ['Avame', 'Azohoue-Aliho', 'Azohoue-Cada', 'Tori-Cada', 'Tori-Gare', 'Tori-Bossito']],
        ['name' => 'Ze', 'arrondissements' => ['Adjan', 'Dawe', 'Djigbe', 'Dodji-Bata', 'Hekanme', 'Koundokpoe', 'Sedje-Denou', 'Sedje-Houegoudo', 'Tangbo', 'Yokpo', 'Ze']],
    ]],
    ['name' => 'Borgou', 'communes' => [
        ['name' => 'Bembereke', 'arrondissements' => ['Beroubouay', 'Bouanri', 'Gamia', 'Ina', 'Bembereke']],
        ['name' => 'Kalale', 'arrondissements' => ['Basso', 'Bouca', 'Derassi', 'Dunkassa', 'Peonga', 'Kalale']],
        ['name' => 'N\'Dali', 'arrondissements' => ['Bori', 'Gbegourou', 'Ouenou', 'Sirarou', 'N\'Dali']],
        ['name' => 'Nikki', 'arrondissements' => ['Biro', 'Gnonkourokali', 'Ouenou', 'Serekali', 'Suya', 'Tasso', 'Nikki']],
        ['name' => 'Parakou', 'arrondissements' => ['1er arrondissement', '2e arrondissement', '3e arrondissement']],
        ['name' => 'Perere', 'arrondissements' => ['Gninsy', 'Guinagourou', 'Kpebie', 'Pane', 'Sontou', 'Perere']],
        ['name' => 'Sinende', 'arrondissements' => ['Fo-Boure', 'Sekere', 'Sikki', 'Sinende']],
        ['name' => 'Tchaourou', 'arrondissements' => ['Alafiarou', 'Beterou', 'Goro', 'Kika', 'Sanson', 'Tchatchou', 'Tchaourou']],
    ]],
    ['name' => 'Collines', 'communes' => [
        ['name' => 'Bante', 'arrondissements' => ['Agoua', 'Akpassi', 'Atokolibe', 'Bobe', 'Gouka', 'Koko', 'Lougba', 'Pira', 'Bante']],
        ['name' => 'Dassa-Zoume', 'arrondissements' => ['Akoffodjoule', 'Gbaffo', 'Kere', 'Kpingni', 'Lema', 'Paouingnan', 'Soclogbo', 'Tre', 'Dassa I', 'Dassa Ii']],
        ['name' => 'Glazoue', 'arrondissements' => ['Aklampa', 'Assante', 'Gome', 'Kpakpaza', 'Magoumi', 'Ouedeme', 'Sokponta', 'Thio', 'Zaffe', 'Glazoue']],
        ['name' => 'Ouesse', 'arrondissements' => ['Challa-Ogoi', 'Djegbe', 'Gbanlin', 'Ikemon', 'Kilibo', 'Laminou', 'Odougba', 'Toui', 'Ouesse']],
        ['name' => 'Savalou', 'arrondissements' => ['Djalloukou', 'Doume', 'Gobada', 'Kpataba', 'Lahotan', 'Lema', 'Logozohe', 'Monkpa', 'Ottola', 'Ouesse', 'Tchetti', 'Savalou-Aga', 'Savalou-Agbado', 'Savalou-Attake']],
        ['name' => 'Save', 'arrondissements' => ['Besse', 'Kaboua', 'Offe', 'Okpara', 'Sakin', 'Adido', 'Boni', 'Plateau']],
    ]],
    ['name' => 'Couffo', 'communes' => [
        ['name' => 'Aplahoue', 'arrondissements' => ['Atomey', 'Azove', 'Dekpo-Centre', 'Godohou', 'Kissamey', 'Lonkly', 'Aplahoue']],
        ['name' => 'Djakotomey', 'arrondissements' => ['Adjintimey', 'Betoumey', 'Gohomey', 'Houegamey', 'Kinkinhoue', 'Kokohoue', 'Kpoba', 'Sokouhoue', 'Djakotomey I', 'Djakotomey Ii']],
        ['name' => 'Dogbo', 'arrondissements' => ['Ayomi', 'Deve', 'Honton', 'Lokogohoue', 'Madjre', 'Totchangni Centre', 'Tota']],
        ['name' => 'Klouekanmey', 'arrondissements' => ['Adjahonme', 'Ahogbeya', 'Ayahohoue', 'Djotto', 'Hondjin', 'Lanta', 'Tchikpe', 'Klouekanme']],
        ['name' => 'Lalo', 'arrondissements' => ['Adoukandji', 'Ahodjinnako', 'Ahomadegbe', 'Banigbe', 'Gnizounme', 'Hlassame', 'Lokogba', 'Tchito', 'Tohou', 'Zalli', 'Lalo']],
        ['name' => 'Toviklin', 'arrondissements' => ['Adjido', 'Avedjin', 'Doko', 'Houedogli', 'Missinko', 'Tannou-Gola', 'Toviklin']],
    ]],
    ['name' => 'Donga', 'communes' => [
        ['name' => 'Bassila', 'arrondissements' => ['Aledjo', 'Manigri', 'Penessoulou', 'Bassila']],
        ['name' => 'Copargo', 'arrondissements' => ['Anandana', 'Pabegou', 'Singre', 'Copargo']],
        ['name' => 'Djougou', 'arrondissements' => ['Barei', 'Barienou', 'Bellefoungou', 'Bougou', 'Koloconde', 'Onklou', 'Partago', 'Pelebina', 'Serou', 'Djougou I', 'Djougou Ii', 'Djougou Iii']],
        ['name' => 'Ouake', 'arrondissements' => ['Badjoude', 'Komde', 'Semere 1', 'Semere 2', 'Tchalinga', 'Ouake']],
    ]],
    ['name' => 'Littoral', 'communes' => [
        ['name' => 'Cotonou', 'arrondissements' => ['1er arrondissement', '2e arrondissement', '3e arrondissement', '4e arrondissement', '5e arrondissement', '6e arrondissement', '7e arrondissement', '8e arrondissement', '9e arrondissement', '10e arrondissement', '11e arrondissement', '12e arrondissement', '13e arrondissement']],
    ]],
    ['name' => 'Mono', 'communes' => [
        ['name' => 'Athieme', 'arrondissements' => ['Adohoun', 'Atchannou', 'Dedekpoe', 'Kpinnou', 'Athieme']],
        ['name' => 'Bopa', 'arrondissements' => ['Agbodji', 'Badazouin', 'Gbakpodji', 'Lobogo', 'Possotome', 'Yegodoe', 'Bopa']],
        ['name' => 'Come', 'arrondissements' => ['Agatogbo', 'Akodeha', 'Ouedeme-Pedah', 'Oumako', 'Come']],
        ['name' => 'Grand-Popo', 'arrondissements' => ['Adjaha', 'Agoue', 'Avlo', 'Djanglanmey', 'Gbehoue', 'Sazue', 'Grand-Popo']],
        ['name' => 'Houeyogbe', 'arrondissements' => ['Dahe', 'Doutou', 'Honhoue', 'Zoungbonou', 'Houeyogbe', 'Se']],
        ['name' => 'Lokossa', 'arrondissements' => ['Agame', 'Houin', 'Koudo', 'Ouedeme-Adja', 'Lokossa']],
    ]],
    ['name' => 'Oueme', 'communes' => [
        ['name' => 'Adjarra', 'arrondissements' => ['Aglogbe', 'Honvie', 'Malanhoui', 'Mededjonou', 'Adjarra 1', 'Adjarra 2']],
        ['name' => 'Adjohoun', 'arrondissements' => ['Akpadanou', 'Awonou', 'Azowlisse', 'Deme', 'Gangban', 'Kode', 'Togbota', 'Adjohoun']],
        ['name' => 'Aguegues', 'arrondissements' => ['Avagbodji', 'Houedome', 'Zoungame']],
        ['name' => 'Akpro-Misserete', 'arrondissements' => ['Gome-Sota', 'Katagon', 'Vakon', 'Zoungbome', 'Akpro-Misserete']],
        ['name' => 'Avrankou', 'arrondissements' => ['Atchoukpa', 'Djomon', 'Gbozoume', 'Kouti', 'Ouanho', 'Sado', 'Avrankou']],
        ['name' => 'Bonou', 'arrondissements' => ['Affame', 'Atchonsa', 'Dame-Wogon', 'Hounvigue', 'Bonou']],
        ['name' => 'Dangbo', 'arrondissements' => ['Dekin', 'Gbeko', 'Houedomey', 'Hozin', 'Kessounou', 'Zoungue', 'Dangbo']],
        ['name' => 'Porto-Novo', 'arrondissements' => ['1er arrondissement', '2e arrondissement', '3e arrondissement', '4e arrondissement', '5e arrondissement']],
        ['name' => 'Seme-Podji', 'arrondissements' => ['Agblangandan', 'Aholouyeme', 'Djeregbe', 'Ekpe', 'Tohoue', 'Seme-Podji']],
    ]],
    ['name' => 'Plateau', 'communes' => [
        ['name' => 'Adja-Ouere', 'arrondissements' => ['Ikpinle', 'Kpoulou', 'Masse', 'Oko-Akare', 'Tatonnonkon', 'Adja-Ouere']],
        ['name' => 'Ifangni', 'arrondissements' => ['Banigbe', 'Daagbe', 'Ko-Koumolou', 'Lagbe', 'Tchaada', 'Ifangni']],
        ['name' => 'Ketou', 'arrondissements' => ['Adakplame', 'Idigny', 'Kpankou', 'Odometa', 'Okpometa', 'Ketou']],
        ['name' => 'Pobe', 'arrondissements' => ['Ahoyeye', 'Igana', 'Issaba', 'Towe', 'Pobe']],
        ['name' => 'Sakete', 'arrondissements' => ['Aguidi', 'Ita-Djebou', 'Takon', 'Yoko', 'Sakete 1', 'Sakete 2']],
    ]],
    ['name' => 'Zou', 'communes' => [
        ['name' => 'Abomey', 'arrondissements' => ['Agbokpa', 'Detohou', 'Sehoun', 'Zounzonme', 'Djegbe', 'Hounli', 'Vidole']],
        ['name' => 'Agbangnizoun', 'arrondissements' => ['Adanhondjigo', 'Adingnigon', 'Kinta', 'Kpota', 'Lissazounme', 'Sahe', 'Sinwe', 'Tanve', 'Zoungoundo', 'Agbangnizoun']],
        ['name' => 'Bohicon', 'arrondissements' => ['Agongointo', 'Avogbanna', 'Gnidjazoun', 'Lissezoun', 'Ouassaho', 'Passagon', 'Saclo', 'Sodohome', 'Bohicon I', 'Bohicon Ii']],
        ['name' => 'Cove', 'arrondissements' => ['Houeko', 'Adogbe', 'Gounli', 'Houin-Hounso', 'Lainta-Cogbe', 'Naogon', 'Soli', 'Zogba']],
        ['name' => 'Djidja', 'arrondissements' => ['Agondji', 'Agouna', 'Dan', 'Dohouime', 'Gobaix', 'Houto', 'Monsourou', 'Mougnon', 'Oumbegame', 'Setto', 'Zounkon', 'Djidja Centre']],
        ['name' => 'Ouinhi', 'arrondissements' => ['Dasso', 'Sagon', 'Tohoues', 'Ouinhi Centre']],
        ['name' => 'Zagnanado', 'arrondissements' => ['Agonlin-Houegbo', 'Baname', 'Don-Tan', 'Dovi', 'Kpedekpo', 'Zagnanado Centre']],
        ['name' => 'Za-Kpota', 'arrondissements' => ['Allahe', 'Assanlin', 'Houngome', 'Kpakpame', 'Kpozoun', 'Za-Tanta', 'Zeko', 'Za-Kpota']],
        ['name' => 'Zogbodomey', 'arrondissements' => ['Akiza', 'Avlame', 'Cana I', 'Cana Ii', 'Dome', 'Koussoukpa', 'Kpokissa', 'Massi', 'Tanwe-Hessou', 'Zoukou', 'Zogbodomey Centre']],
    ]],
];
