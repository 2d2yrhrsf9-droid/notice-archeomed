<?php
/**
 * Les prénoms que l'on connaît, pour un avis sur celui qu'on a tapé.
 *
 * La liste réunit les prénoms donnés en France à mille enfants au moins
 * depuis 1900, et des prénoms étrangers d'usage courant : environ trois
 * mille cinq cents, en minuscules et sans accents. Elle ne juge jamais : un
 * prénom qu'elle ignore n'est pas faux, il est rare ou étranger (Katarzyna,
 * Iñaki, Wandrille), et l'on se tait. L'avis ne retient pas l'envoi.
 *
 * Deux avis, et un seul par personne :
 *  - le prénom est un nom de famille fréquent, et le nom un prénom connu :
 *    les deux champs semblent échangés (« Durand », « Martin ») ;
 *  - le prénom est connu sans accent, et la liste ne le connaît qu'avec un
 *    seul (« Helene », « Hélène ») : on propose cette forme. Le tréma n'est
 *    jamais proposé — Mael, Loic, Anais sont des graphies d'usage — ni les
 *    formes étrangères qui s'écrivent sans accent (Andrea, Felix, Kevin).
 *
 * Le navigateur reçoit les mêmes listes (pour_le_navigateur()) et applique
 * les mêmes règles : l'auteur lit en tapant ce que la rédaction lit ensuite.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Prenoms {

	/** Les lettres accentuées, et leur lettre nue, au même rang. */
	const ACCENTS = 'àáâäãåāăąçćčďđèéêëēėęěìíîïīįñńňòóôöõøōőùúûüūůűýÿžźżšśşťřłğ';
	const NUES    = 'aaaaaaaaacccddeeeeeeeeiiiiiinnnoooooooouuuuuuuyyzzzssstrlg';

	/** Les prénoms connus, en minuscules et sans accents. */
	const CONNUS = <<<'LISTE'
aaliyah aaron abby abd abdallah abdel abdelaziz abdelhakim abdelhamid abdelkader abdelkarim
abdelkrim abdellah abderrahmane abdou abdoul abdoulaye abdullah abel abigael abigaelle abigail
abou aboubacar aboubakar abraham aby achille achraf ada adam adama adame adan adel adelaide
adele adelie adelina adeline adem aden adil adnan adnane adolphe adolphine adonis adrian adriana
adriano adriel adrien adrienne aelys agathe aglae agnes ahlem ahmad ahmed ahmet aicha aida aidan
aiden aimable aime aimee aimie aimy aina ainhoa aisha aisling aissa aissata aissatou akim akram
aksel aksil alaa alain alan alana alaric alasdair alassane alba alban albane albanie alberic
albert alberta alberte albertine alberto albin albine alcide aldo aldric alec alejandro
alessandro alessia alessio alex alexa alexander alexandra alexandre alexandrine alexane alexi
alexia alexiane alexie alexina alexine alexis alexy aleyna alfred alfreda alfredo ali alia alice
alicia alida alienor aliette alima alina aline alisa alisha alison alissa alissia alisson
alistair alix alixe aliya aliyah alize alizee allain allan allison alma alois aloise alonzo
aloys aloyse alpha alphonse alphonsine alton alun alvin alwena aly alya alyah alycia alyson
alyssa alyssia alysson amadou amael amaia amal amalia amalya amand amanda amandine amani amar
amara amaury amaya amber ambre ambrine ambroise ambroisine amede amedee amel amelia amelie
ameline amelle amin amina aminata amine amir amira amjad amy ana anabelle anae anael anaelle
anais anas anass anastasia anastasie anatole anaya andre andrea andreas andree andrew andy anes
ange angel angela angele angelica angelina angeline angelique angelo angie ania anicet anick
anis aniss anissa anita ann anna annabel annabelle annaelle anne annelise annette annic annick
annie annik annita annonciade anny anouar anouchka anouck anouk anselme anthea anthime anthonin
anthony antoine antoinette anton antone antonella antoni antonia antonin antonine antonio antony
anya aoife apolline appoline april archibald archie arda aria ariana ariane ariel arielle aris
aristide arlene arlette armance armand armande armandine armando armel armelle arnaud arnauld
arnault arno arnold aron arsene arthur arthus artur arturo arwen arya ashley asma asmaa assa
assia assil assiya assya astrid astride asya athanase athena athenais aubin aubrey aude audrey
audric augusta auguste augustin augustine aurel aurele aurelia aurelie aurelien auriane aurora
aurore auxane auxence ava avril awa awen axel axelle aya ayah ayan ayana ayaz aydan ayden ayla
aylan aylin ayline ayman aymane aymen aymeric aymerick ayna ayoub ayrton ayse azad aziliz aziz
aziza azra azzedine bachir badis badr bakary balthazar baptiste baptistin baptistine barbara
barbe barnabe barry barthelemy basile basma bastian bastien batiste baudouin baya beatrice
beatrix becky belen belinda belkacem bella ben benedicte benjamin bennie benny benoist benoit
benoite berangere berat berenger berengere berenice bernadette bernard bernardine bernice bert
bertha berthe bertille bertin bertrand bessie beth bethany betsy bette bettie bettina betty
bettye beulah beverly beyza bianca bilal bilel bill billal billel billie billy binta bintou
blaise blanche blandine bleuenn bob bobbie bobby boniface bonita bonnie boris boubacar bouchra
brad bradley brahim brandi brandon brandy brayan brenda brendan brent bret brett briac brian
brice bridget brieuc brigitte brittany bronwen brooke bruce bruna brune bruno bryan burak byron
caleb cali calie calista calixte callie calvin calypso camelia cameron camil camila camilia
camilla camille candace candice candy cannelle capucine cara carine carinne carl carla carlo
carlos carmela carmelo carmen carol carolane carole carolina caroline carolyn carrie cary
casimir cassandra cassandre cassidy cassie cassiopee cassy castille cataleya catalina catherine
cathia cathie cathleen cathy cecelia cecil cecile cecilia cedric cedrick celeste celestin
celestine celia celian celie celina celine celya cendrine cerise cerys cesar cesarine chad
chadia chahid chahine chahinez chaima chakib chanel chantal chantale charlemagne charlene
charles charlette charley charli charlie charline charlize charlotte charly charlyne chayma
cheick cheikh chelsea chelsy cheri cherie cherif cherifa cherine cheryl chester cheyenne chiara
chirine chloe chris christa christel christele christelle christian christiane christianne
christie christina christine christoph christophe christopher christy chrystel chrystele
chrystelle ciara ciaran cindy cinthia claire clairette clara clarence clarisse claude claudette
claudia claudie claudine claudio claudius claudy clea clelia clemence clement clementine cleo
clifford clifton clint clinton clive cloe clotaire clothilde clotilde clovis clyde coleen
colette colin coline colleen collette colm colombe colyne come conception connie constance
constant constantin cora coralie coraline corentin corentine corey corine corinne cormac cory
cosette coumba courtney craig cristelle cristiano cristina crystal curtis cylia cyndie cynthia
cyprien cyprienne cyriaque cyrielle cyril cyrille cyrine czeslaw dafydd dahlia daisy dale dalia
dalil dalila dallas damian damien damon dan dana danae dania daniel daniela daniele daniella
danielle danny dante dany daoud daouda daphne daphnee daria darin darine dario darius darla
darlene darrel darrell darren darrin darryl daryl dave david davina davy dawn dawson dayan
dayana dean deanna debbie debora deborah debra declan dee delbert delia della delores deloris
delphin delphine demba dena denis denise deniz dennis denys denyse derek dermot derrick derya
desire desiree devin dewayne dewey diana diane dianna dianne didier diego dieter dilan dilara
dimitri dimitry dina dino diogo divine dixie djamal djamel djamila djena djeneba djenna djibril
djilali dolores domenico dominic dominick dominique domitille don donald donat donatien
donatienne donia donna donnie donovan dora doreen doria dorian doriane dorine doris dorothea
dorothee dorothy doryan doug douglas dounia driss duane duncan dustin dwayne dwight dylan earl
earnest eda eddie eddy eden edgar edgard edith edithe edmee edmond edmonde edmund edna edouard
eduardo edward edwige edwin eglantine eileen eilidh el ela elaia elaine elbert eldon elea eleana
eleanor eleanore elena eleonore elfriede eli elia elian eliana eliane elianne elias elie elies
eliette elif elijah elina eline elio eliot eliott elisa elisabeth elise elisee elissa eliza
elizabeth ella ellen ellie elliot elliott elly elmer eloan eloane elodie elody eloi eloise elona
elora elouan elouann elsa elsie elvina elvira elvire elvis ely elya elyana elyane elyas elyes
elyna elyne elyo elysa elyse elyssa elza ema emanuel emelie emeline emelyne emeric emerick emie
emil emile emilia emilie emilien emilienne emilio emily emine emir emma emmanuel emmanuella
emmanuelle emmeline emmie emmy emna emre emrys emy enael enea eneko enes enguerrand enis enoha
enola enora enrico enrique enza enzo eoin erell eren eric erica erick ericka erik erika erin
erine erma erna ernest ernestine ernst erwan erwann erwin eslem esma esmee esmeralda esperance
esra essie esteban estella estelle esther etan ethan ethann ethel etienne etiennette etta eugene
eugenia eugenie eula eulalie eunice euphrasie eusebe eustache eva evan evann evans evariste eve
eveline evelyn evelyne even evy ewan ewen ewenn eya eyden eymen ezechiel ezekiel ezio ezra
fabian fabien fabienne fabio fabiola fabrice fadi fadila fahim faith faiza fannie fanny fanta
fantine fany faouzi farah fares farid farida farouk fathi fatih fatiha fatima fatimata fatma
fatna fatou fatoumata faustin faustine faycal faye federico felicia felicie felicien felicienne
felicite felix ferdinand feriel fern fernand fernanda fernande fernando feryel ffion filipe
filippo fiona firdaws firmin flavia flavie flavien flavio fleur fleury flora flore florence
florent florentin florentine florian floriane florianne florie florimond florine floris fortune
fouad foued fouzia franc france franceline frances francesca francesco francette francia
franciane francine francis francisca francisco francisque franck francky franco francois
francoise frank frankie frantz franz fred freda freddie freddy frederic frederick frederik
frederique fredrick fredy frida frieda friedrich fulbert furkan gabin gabriel gabriela gabriele
gabriella gabrielle gaby gael gaelle gaetan gaetane gaia gail garance gareth garry gary gaspar
gaspard gaston gatien gauthier gautier gayle gaylord gene geneva genevieve geoffray geoffrey
geoffroy georg george georges georget georgette georgia georgina georgine geraint gerald
geraldine gerard geraud gerhard gerhild germain germaine gerome gertrude gervais gervaise gery
gethin ghislain ghislaine ghyslaine giani gianni gil gilbert gilberte gilda gildas gilette
gilles gillette gina ginette ginger gino giorgio giovani giovanna giovanni giovanny gisele
giselle gislaine gislhaine giulia giulian giuliana giuliano giulio giuseppe gladys glen glenda
glenn glenna gloria glyn godefroy gontran gonzague gonzalo goulven grace gracie gracieuse
gratien graziella greg gregoire gregory gretchen guadalupe guenael guenaelle guido guilaine
guilhem guillaume guillemette guiseppe guislaine gurvan gustave guy guylaine guylene gwen
gwenael gwenaelle gwendal gwendoline gwendolyn gwenllian gwenn gwennaelle gwenola gwladys habib
habiba haby hacene hadja hadrien hafid hafida hafsa hailey hajar hakan hakim hakima halim halima
hamed hamid hamidou hamish hamza hana hanae hanan hanane hania hanna hannah hans harmonie
harmony harold haron haroun harouna harriet harry hasan hasna hassan hassen hatem hatice hatim
hattie hava hawa hayat hayden hayet hazel heather hector hedi hedwige heidi helen helena helene
helio helios helmut heloise henri henria henrietta henriette henrique henry herbert herman
hermance hermann hermine herminie herve herveline hiba hicham hichem hidaya hilaire hilal hilda
hildegarde hina hind hippolyte hocine holly homer honore honorine hope horace hortense houcine
houda houria hubert huberte hugh hugo hugues huguette humbert huseyin huw hyacinthe iain ian
ianis iban ibrahim ibrahima ibtissam ibtissem ida idir idris idriss idrissa ieuan ifor ignace
igor ihsan ikram ilan ilana ilann ilayda ilham ilhame ilhan ilhem ilian iliana ilias ilies illan
illona ilona ilyan ilyana ilyane ilyas ilyass ilyes ilyess imad iman imane imany imen imene
imogene imran imrane ina inaya inayah india ines iness inez ingrid inna insaf ira irem irena
irene irenee irina iris irma isaac isabel isabella isabelle isaiah isaline isaure ishak ishaq
isidore isis islam islem ismael ismail isra israa issa issam ivan ivana ivy iyad iyed izia jack
jacki jackie jacky jaclyn jacob jacqueline jacquelyn jacques jacquy jad jade jaden jaime jalil
jamal jamel james jamie jamila jan jana jane janelle janet janette janice janick janie janina
janine janique janis janna jannah jannick jany jaouad jared jarod jasmin jasmine jason jassim
javier jawad jawed jay jayden jayne jayson jean jeanette jeanine jeanne jeannette jeannick
jeannie jeannine jeannot jeff jefferson jeffery jeffrey jehan jehanne jenifer jenna jennah
jennie jennifer jenny jennyfer jeoffrey jerald jeremi jeremiah jeremie jeremy jeri jermaine
jerome jeromine jerry jesse jessica jessie jessim jessy jesus jewel jibril jihane jill jim
jimmie jimmy jo joachim joakim joan joana joann joanna joanne joannes joanny joao joaquim
joaquin joceline jocelyn jocelyne jodi jodie jody joe joel joele joelle joey joffrey johan
johana johann johanna johanne johannes john johnathan johnnie johnny johny jolan jolene jon
jonah jonas jonathan jonathon joni jordan jordane jordi jordy jorge joris jose josee josef
josefa joseline joselyne joseph josepha josephe josephine josette joshua josian josiane josianne
josselin josseline josselyne josue josyane joud joy joyce juan juana juanita jude judicael
judith judy jules julia julian juliana juliane juliann julianna julianne julie julien julienne
juliette juline julio julius julyan june junior jurgen justin justine kaan kader kadiatou kadija
kadir kahil kahina kaina kais kalvin kamal kamel kamelia kameron kamil kamila kamilia kaoutar
kara karel karelle karen kari karim karima karin karina karine karl karla kassandra kassim kassy
kataleya katarina kate katell katharina katharine katherine kathia kathleen kathryn kathy katia
katie katrina katy kawtar kawthar kay kayden kayla kaylan kaylia kayliah kayna kayron kays keith
kelia kelian kelli kellie kelly kellya kelvin kelya kelyan ken kenan kendra kenji kenjy kenneth
kenny keny kenza kenzi kenzo kenzy kerem keren keri kerim kerri kerry kessy ketsia ketty kevin
keyla keylan keylia keziah khadidja khadija khaled khalid khalil khalis kheira kiara kieran
kilian killian killyan kilyan kim kimberley kimberly kingsley kirsten klara klaus kleber klervi
kris krista kristel kristell kristen kristi kristie kristin kristina kristine kristopher kristy
krystal kurt kyara kyle kylian kyliann kylie kyllian lachlan ladislas laeticia laetitia lahcen
lahna laia laila laina lalie laly lambert lamia lamine lamya lana lance lancelot landry lara
larbi larissa larry lassana latasha latifa latoya laura laurane lauranne laure laureen laurel
laureline lauren laurena laurence laurene laurent laurentine laurette lauriane laurianne laurie
laurine laury lauryn lauryne laverne lawrence laya layan layana layane layla layna lazare lea
leah leana leandre leandro leane leanna leanne leeloo leelou leeroy lehna leia leigh leila leina
lela leland lelia lena lenaic lenaig leni lenna lenny lenora leny lenzo leo leocadie leon leona
leonard leonardo leonce leone leonel leonide leonie leonne leonor leonore leontine leopold
leopoldine leroy lesley leslie lesly lester leticia levi levy lewis lexie leya leyla leyna lia
liam liana lidia lidwine liham lila lilas lili lilia lilian liliana liliane lilianne lilie lilio
lillian lillie lilly lilou lilwenn lily lilya lina linda lindsay lindsey line linette lino
lionel lionnel lior lisa lisandro lisandru lise lisette lisiane lison lissandro liv livia livio
liya liyah liyana liz liza lizea lizio lizzie loan loana loane loann loanne loetitia loevan
logan lohan lohann loic loick lois loise lola lolita lonnie lony loona lora lorelei loren lorena
lorene lorenza lorenzo loretta lorette lori loriane lorie lorik lorine loris lorna lorraine lory
lorys lotfi lothar lou louan louana louane louann louanne louay loubna louca loucas louie louis
louisa louise louisette louisiane louison louiza louka loukas louna lounes lounis loup lowell
lowen luan luana lubin luc luca lucas luce lucette lucia luciana luciano lucie lucien lucienne
lucile lucille lucinda lucy ludivine ludmila ludmilla ludovic ludwig luigi luis luisa luka lukas
luke lula luna lupe luther lya lyah lyam lyana lyanna lydia lydie lyes lyham lyla lyle lylia
lylian lyliane lylio lylou lyna lynda lyne lynette lynn lynne lysa lysandre lyse lysiane lyvia
lyvio mabel mable maceo maddie maddy madeleine madeline madina madison madjid mady madyson mae
mael maela maelan maelia maelie maeline maelis maeliss maelle maellys maely maelya maelyne
maelys maelyss maena maeva magali magalie magaly magdalena magdeleine maggie maggy maguy
mahamadou mahault mahaut mahdi mahe maher mahmoud maho maia maily mailys maimouna maina mairead
maissa maissane maite maiwen maiwenn majda majid malak malaurie malaury malcolm malcom malek
malia malicia malick malik malika mallaury mallory malo maloe malone malonn malorie malory malou
malvina malya mama mamadou mamie manal mandy manel manelle manoa manoah manoe manolo manon
mansour manuel manuela manuella manuelle mao mara maram marc marceau marcel marcelin marceline
marcella marcelle marcellin marcelline marcia marco marcus mareva margaret margarete margarita
margaux margie margo margot marguerite maria mariam mariama mariame marian mariana mariane
marianna marianne mariannick mariano marie marielle mariem marien mariette marika marilene
marilou marilyn marilyne marin marina marine marinette mario marion marise marius mariya
marjolaine marjorie marjory mark marla marlene marley marlon marlyse maroua marouane marsha
marta martha marthe martial martin martine marty marvin marvyn marwa marwan marwane mary marya
maryam maryann maryannick marylene marylin maryline marylise marylou maryne maryse maryvonne
massimo mateo matheo mathew mathias mathieu mathilda mathilde mathis mathurin mathys matias
matilda matilde matis matisse matt matteo mattheo matthew matthias matthieu matthis mattia
mattie mattis matys maud maude maurane maureen maurice mauricette maurine max maxance maxence
maxens maxim maxime maximilien maximilienne maximin maxine may maya mayar mayeul maylan maylie
mayline maylis maylon maylone mayra mayron mayssa mayssane medard meddy mederic medhi medhy
medina medine megan megane meghan mehdi mehdy mehmet meissa melaine melanie melany melba
melchior melek melia melih melina melinda meline melis melisa melisande melissa melissandre
mellina melodie melody melvin melvyn melya melyna melyne melyssa mendy mercedes meriam meriem
merle merlin merve merwan meryam meryem meryl messaoud messaouda mewen mia michael michaela
micheal michel michele micheline michelle mickael mickey miguel mikael mikail mike mila milan
milana milann mildred milena milene milhan milhane milla milo miloud milton mina mindy minnie
mira miral miran mireille mirella miriam misty miya moana modeste mohamed mohammad mohammed
mohand moira moise molly mona monia monica monique morad morgan morgane morgann mouhamadou
mouhamed mouhammad moulay mouloud mouna mounia mounir mourad moussa moustafa moustapha muguette
muhammad muhammed murat murdo muriel murielle mustafa mustapha mya myla mylan mylann mylene mylo
myra myriam myriame myriem myrna myron myrtille myrtle nabil nabila nacer nacera nacim nada
nadege nadia nadine nadir nael naelle nahel nahia nahil nahla nahyl naia nail naila naim naima
nais najat najib najwa nancy nans nao naomi naomie naomy naoual naoufel narcisse nasser nassera
nassim nassima nassira nastasia natacha natalia natalie natan natasha nateo nathael nathalie
nathan nathanael nathanaelle nathaniel natheo nawal nawel nawelle nawfel naya nayah nayel nayla
nazim neela neil neila nejma nelia nell nella nellie nelly nelson nelya neo nesrine nessa nessim
nestor nettie neyla niall niamh nicaise nicholas nichole nick nicola nicolas nicole nicolle
niels nigel nihel nikita nikki nikola nil nils nina nino ninon nisa nisrine nizar noa noah noam
noan noe noel noela noele noelia noelie noeline noella noelle noellie noemi noemie noemy noha
noham nohan nohlan nola nolan nolann nolhan nolwen nolwenn noor nora norah norbert nordine noria
norma norman nour noura nourdine noureddine nouria numa nuno oceane oceanne octave octavia
octavie odette odile odille oihan oisin olfa olga olive oliver olivia olivier ollie ollivier
olympe omar ombeline omer ondine opal ophelia ophelie ora orane oriana oriane orianne orla
orlando orlane ornella orso orville oscar osman othman othmane othon otis otto oualid ouarda
oumar oumayma oumou ousmane oussama owain owen ozan pablo paco pacome padraig palmyre paloma pam
pamela paola paolo paquerette pascal pascale pascaline pat patrice patricia patrick patsy patti
patty paul paula paule paulette paulin pauline paulo pearl pedro peggy pelagie penelope penny
percy perle perrine pete peter petra petronille pharell philemon philibert philip philipp
philippe philippine phillip philomene phyllis pia piero pierre pierrette pierric pierrick
pierrine pierrot pietro pilar pio placide pol polly prescilia prescilla prescillia preston priam
prince prisca priscilia priscilla priscille priscillia prosper prudence prune qassim quentin
quitterie rabah rabia rachael rachel rachelle rachid rachida radia radouane rafael rafik rahim
rahma raissa raja ralph ramata ramazan rami ramon ramona ramy ramzi randal randall randy rania
ranya raoul raphael raphaele raphaelle raquel raul rayan rayane rayen rayhan rayhana raymond
raymonde raynald razane reba rebecca rebekah reda redouane regina reginald regine regis reine
rejane remi remy rena renald renan renato renaud rene renee renelle rex reynald rhiannon rhonda
rhys riad ricardo richard rick rickey ricky rida rigobert rihanna riley rim rina rita riyad
robbie robert roberta roberte roberto robin robinson robyn roch rochelle roderick rodney
rodolphe rodrigo rodrigue roger rohan roisin roland rolande rolland rollande romain romaine
roman romane romaric romeo romie romuald romy ron ronald ronan ronda ronnie rony roosevelt rosa
rosaire rosalia rosalie rosalind rosario rose roseline roselyne rosemarie rosemary rosemonde
rosetta rosette rosie rosine rosita rosy roxane roxanne rozenn ruairi ruben ruby ruddy rudolph
rudy rufus rui ruth ryad ryan rym saad sabah sabine sabri sabrina sabrine sacha sadia sadie
sadio safa safaa safia safiya safwan sahra said saida saint sainte saja sajid sakina salah salem
saliha salim salima salimata sallie sally salma salman salome salomon saloua salvador salvatore
salwa sam samantha samba sami samia samir samira sammie sammy samson samuel samy samya sana
sanaa sanae sandie sandra sandrina sandrine sandro sandy sania santa santiago saoirse sara sarah
sarra sasha satine saturnin saul sauveur savannah scarlett scholastique scott seamus sean
sebastian sebastien segolene sekou selen selena selene selim selin selma selyan semih sena
sephora seraphin seraphine serena serge sergine sergio serine serkan servane seth sevan severin
severine seydou shaima shaina shana shane shanice shanna shannon shanon shari sharon shaun shawn
shawna shayma shayna sheila shelby shelia shelley shelly sherazade sheri sherine sherri sherrie
sherry sheryl sheryne shirel shirley siana sibel sibylle sidi sidney sidonie sidra siegfried
sienna sigrid siham sihame sihem sila silas silvain silvana silvere silvia silvio simeon simon
simone simonne sinan sindy sinead siobhan sira sirine sixtine slimane sloan sloane smail smain
soan soane soann soazig soen sofia sofian sofiane sofien sohan sohane sohann soizic solal solan
solange solen solene solenn solenne soline solveig sondra sonia sonja sonny sony sonya sophia
sophian sophiane sophie soraya soren sosthene souad soufiane souhayl soukaina soulayman
souleyman souleymane souleymen soumaya soumeya soumia stacey stacie stacy stan stanislas
stanislawa stanley stecy steeve steeven stefan stefanie stefano steffy stella stephan stephane
stephanie stephen sterenn stessy steve steven stevens sue suleyman sullivan sully sullyvan
sulyvan susan susanne susie suzanne suzelle suzette suzie suzon suzy sven swan swann syana
sybille sydney sylia sylvain sylvaine sylvana sylvanie sylvere sylvestre sylvette sylvia
sylviane sylvianne sylvie sylvine sylvio syrine tabitha taha tahar taina tais talia talya tamara
tami tammie tammy tanguy tania tanya tao tara tarek tarik tasha tasnim tasnime tatiana tayeb
taylor tayron tea ted teddy telio teo terence teresa teri terrance terrence terri terry tesnim
tesnime tess tessa thais thalia thalya thao thea thelio thelma theo theodor theodora theodore
theophane theophile theotime theresa therese thereze thi thiago thibaud thibault thibaut thierry
thiery thimeo thomas thymeo tia tiago tiana tidiane tifany tifenn tiffanie tiffany tilio tim
timael timeo timmy timote timothe timothee timothy tina tino tiphaine tiphanie titouan tiziano
tobias toby todd tom tomas tommie tommy tomy toni tonia tony tonya toufik toussaint toussainte
tracey traci tracie tracy travis trevor tricia trina trisha tristan troy trudy trystan tudur tya
tyana tyler tylian tylio tymeo typhaine typhanie tyron tyrone ugo ulrich ulysse umut urbain
ursula ursule vadim valentin valentina valentine valentino valere valerian valerie valery van
vanessa vanille vanina velma vera verna vernon veronica veronique vianney vicente vicki vickie
vicky victoire victor victoria victorien victorin victorine viktor vincent vincente vincenzo
vinciane viola violaine violet violette virgil virgile virginia virginie vital vivian viviane
vivianne vivien vivienne vladimir von wael wafa wail walid walter wanda warda warren wassil
wassila wassim wayne wendell wendy werner wesley whitney wilbert wilbur wilfred wilfrid wilfried
wilhelm willard willem william williams willie willow willy wilma wilson winifred wissal wissam
wissem wolfgang wyatt xavier xaviere yacine yacoub yael yaelle yahia yahya yamina yamine yan
yani yanick yanis yaniss yann yannick yannis yara yasin yasmin yasmina yasmine yasser yassin
yassine yassir yaya yazid yelena ylan ylann yliana ylies ylona yoan yoann yohan yohann yolaine
yolanda yolande yolene yona yoni youcef youenn youmna youna younes youness youri yousra youssef
youssouf youssra ysaline ysee yuna yunus yusuf yvan yvann yveline yvelise yves yvette yvon
yvonne yvonnick zacharie zachary zack zahia zahra zaina zakaria zakariya zakarya zakia zara
zayan zayd zayn zaynab zayneb zehra zelia zelie zeyneb zeynep zia ziad zina zineb zinedine ziyad
zoe zohra zola zora zoubida zyad
LISTE;

	/**
	 * Des prénoms qui sont aussi des noms de lieu ou de famille : connus
	 * comme prénoms, ils ne font pas croire, dans le champ du nom, à un
	 * prénom et un nom échangés (« Fleury », « Lorraine »).
	 */
	const ECARTES = <<<'LISTE'
carolina chelsea el fleury france georgia jordan lorraine maya milan nancy nil orlando prince
roman saint sainte santa sidney sony van victoire virginia von willow
LISTE;

	/**
	 * Des noms de famille fréquents qui ne sont pas des prénoms, les mots
	 * d'un nom composé joints par « _ » : tapés comme prénom, ils font
	 * croire aux deux champs échangés.
	 */
	const NOMS = <<<'LISTE'
aubert aubry bailly barbier baron barre baudry benard berger besson bihan blanc blanchard bonnet
boucher bouchet boulanger bourdon bourgeois bouvet bouvier boyer breton briand brun brunet
carlier caron carpentier carre charpentier chauvet chauvin chevalier chevallier collet collin
cordier cousin da_silva delaunay delmas deschamps devaux dubois dubreuil dufour dumas dumont
dupont dupuis dupuy durand durant duval fabre faure fernandez ferrand ferreira fontaine fournier
gaillard garcia garnier gay gillet girard giraud gonzalez grondin gros guerin guichard guillot
guillou guyot hamon hebert hoarau huet jacquet jaouen joly kerboul klein lacroix laine lamy
langlois laporte laurens le_bihan le_borgne le_corre le_floch le_gac le_gall le_goff le_moal
le_roux leblanc lebon lebreton lebrun leclerc leclercq lecomte leduc lefebvre lefevre leger
legrand lejeune lemaire lemaitre lemoine leroi leroux leveque lopez maillard mallet marchal
marchand marechal martinez masse masson menard mercier meunier meyer michaud millet monnier
moreau morel moreno morin morvan moulin muller navarro paris pasquier payet pelletier perez
perret perrier perrin perrot petit picard pichon poirier pons poulain prevost renard renault rey
reynaud riou riviere roche rodriguez rossi rousseau roussel roux roy royer sanchez schmitt
schneider teixeira tessier vallet vasseur vidal weber
LISTE;

	/** La seule forme accentuée que la liste connaisse à ces prénoms. */
	const ACCENTUES = <<<'LISTE'
adèle adélie aimé aimée alizée aliénor amélie anaé andré andrée angèle angéline angélique arsène
aurèle aurélia aurélie aurélien béatrice bénédicte bérangère bérengère bérénice camélia
cassiopée charlène christèle cléa clémence clément clémentine cécile cédric célestin célestine
célian céline célya césar côme daphné dorothée désiré eléa eléana eléanore eléonore esmée eugène
eugénie françois françoise frédéric frédérique félicie geneviève gisèle grégoire géraldine
gérard hanaé hervé hélène imène josué josé joséphine jérémie jérôme kamélia kélia kélya laurène
lizéa léana léandre léane léanna léanne lény léonard léone léontine léopold mahé maloé manoé
marlène marylène mathéo matthéo mattéo miléna mylène mégane mélanie mélia mélina méline mélodie
mélya mélyna mélyne nadège nathéo natéo noé noélie noéline noémie nélia nélya océane ophélie
pacôme philomène pénélope rené régine régis rémi rémy salomé siméon solène stéphane stéphanie
sébastien ségolène sélène séléna séréna séverine thiméo thyméo thélio théodore théophile thérèse
timothé timothée timoté timéo tyméo télio valérie véronique ysée zélie
LISTE;

	/** Les listes, lues une fois. */
	private static $listes = null;

	/** Les listes en tables, la clé étant le prénom sans accents. */
	private static function listes() {
		if ( null === self::$listes ) {
			$table = function ( $texte ) {
				$l = preg_split( '/\s+/', trim( $texte ) );
				return array_fill_keys( $l, true );
			};
			$accentues = array();
			foreach ( preg_split( '/\s+/', trim( self::ACCENTUES ) ) as $forme ) {
				$accentues[ self::cle( $forme ) ] = $forme;
			}
			self::$listes = array(
				'connus'    => $table( self::CONNUS ),
				'ecartes'   => $table( self::ECARTES ),
				'noms'      => $table( self::NOMS ),
				'accentues' => $accentues,
			);
		}
		return self::$listes;
	}

	/**
	 * Un prénom en minuscules et sans accents : « Hélène » devient
	 * « helene », « Œdipe » « oedipe ». La table est celle du navigateur.
	 */
	public static function cle( $texte ) {
		static $table = null;
		if ( null === $table ) {
			$table = array_combine(
				preg_split( '//u', self::ACCENTS, -1, PREG_SPLIT_NO_EMPTY ),
				preg_split( '//u', self::NUES, -1, PREG_SPLIT_NO_EMPTY )
			);
			$table += array( 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', '’' => "'" );
		}
		$texte = (string) $texte;
		return strtr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $texte, 'UTF-8' ) : strtolower( $texte ), $table );
	}

	/**
	 * Les parties d'un prénom ou d'un nom qui se jugent : « Jean-Pierre »
	 * en donne deux ; une initiale (« J. », « P ») n'en donne aucune. Null
	 * pour ce qui n'est pas fait que de lettres, ou qui joint deux personnes :
	 * « Jean, Marie », « Jean et Marie » se signalent déjà comme telles.
	 */
	private static function parties( $texte ) {
		$texte = trim( (string) $texte );
		if ( '' === $texte || ! preg_match( '/(*UCP)^[\p{L}\s\'’.-]+$/u', $texte )
			|| preg_match( '/(*UCP)\s(?:et|and)\s/u', $texte ) ) {
			return null;
		}
		$parties = array();
		foreach ( preg_split( '/(*UCP)[\s-]+/u', $texte, -1, PREG_SPLIT_NO_EMPTY ) as $partie ) {
			if ( '.' !== substr( $partie, -1 ) && mb_strlen( $partie, 'UTF-8' ) > 1 ) {
				$parties[] = $partie;
			}
		}
		return $parties;
	}

	/**
	 * L'avis sur le prénom d'une personne, ou rien.
	 *
	 * @param string $prenom Le prénom tel que tapé.
	 * @param string $nom    Le nom, pour voir s'il n'est pas le prénom.
	 * @param string $qui    « du responsable », « du co-auteur »…
	 * @return string[]
	 */
	public static function avis( $prenom, $nom, $qui ) {
		$prenom  = trim( (string) $prenom );
		$parties = self::parties( $prenom );
		// Sans mbstring, la casse d'une lettre accentuée ne se lit pas : pas
		// d'avis plutôt qu'un avis faux.
		if ( empty( $parties ) || ! function_exists( 'mb_strtolower' ) ) {
			return array();
		}
		$l        = self::listes();
		$inconnue = false;
		$cles     = array();
		foreach ( $parties as $partie ) {
			$cles[] = self::cle( $partie );
			if ( ! isset( $l['connus'][ end( $cles ) ] ) ) {
				$inconnue = true;
			}
		}
		// Un nom de famille fréquent tapé comme prénom, un prénom comme nom :
		// les champs échangés. Un prénom inconnu, lui, est rare ou étranger,
		// et l'on se tait.
		if ( isset( $l['noms'][ implode( '_', $cles ) ] ) ) {
			$nom   = trim( (string) $nom );
			$siens = self::parties( $nom );
			$tous  = ! empty( $siens );
			foreach ( (array) $siens as $partie ) {
				$c = self::cle( $partie );
				if ( ! isset( $l['connus'][ $c ] ) || isset( $l['ecartes'][ $c ] ) ) {
					$tous = false;
				}
			}
			if ( $tous ) {
				return array( sprintf( 'Le nom %1$s (« %2$s ») est d’ordinaire un prénom : merci de vérifier que le prénom et le nom ne sont pas inversés.', $qui, $nom ) );
			}
		}
		if ( $inconnue ) {
			return array();
		}
		// Partie par partie, les séparateurs gardés : « Jean-Francois »
		// devient « Jean-François », la capitale tapée reste la sienne.
		$morceaux = preg_split( '/(*UCP)([\s-]+)/u', $prenom, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $morceaux as $i => $partie ) {
			$c = self::cle( $partie );
			if ( isset( $l['accentues'][ $c ] ) && $c === mb_strtolower( $partie, 'UTF-8' ) ) {
				$morceaux[ $i ] = mb_substr( $partie, 0, 1, 'UTF-8' ) . mb_substr( $l['accentues'][ $c ], 1, null, 'UTF-8' );
			}
		}
		$propose = implode( '', $morceaux );
		if ( $propose !== $prenom ) {
			return array( sprintf( 'La graphie du prénom %1$s (« %2$s ») ne semble pas habituelle : s’écrit-il « %3$s » ? Merci de vérifier.', $qui, $prenom, $propose ) );
		}
		return array();
	}

	/** Les mêmes listes, pour le formulaire. */
	public static function pour_le_navigateur() {
		return array(
			'accents'   => self::ACCENTS,
			'nues'      => self::NUES,
			'connus'    => preg_replace( '/\s+/', ' ', trim( self::CONNUS ) ),
			'ecartes'   => preg_replace( '/\s+/', ' ', trim( self::ECARTES ) ),
			'noms'      => preg_replace( '/\s+/', ' ', trim( self::NOMS ) ),
			'accentues' => preg_replace( '/\s+/', ' ', trim( self::ACCENTUES ) ),
		);
	}
}
